<?php
/**
 * MageClone MagentoMigrator Sync Service
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Sync;

use MageClone\MagentoMigrator\Api\SyncServiceInterface;
use MageClone\MagentoMigrator\Api\SyncStatusRepositoryInterface;
use MageClone\MagentoMigrator\Api\Data\SyncStatusInterfaceFactory;
use MageClone\MagentoMigrator\Api\Data\SyncStatusInterface;
use MageClone\MagentoMigrator\Api\SyncLogRepositoryInterface;
use MageClone\MagentoMigrator\Api\Data\SyncLogInterface;
use MageClone\MagentoMigrator\Model\Config;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Primary service for orchestrating entity synchronization.
 *
 * Manages the sync lifecycle, dependency ordering, and status tracking.
 */
class SyncService implements SyncServiceInterface
{
    /**
     * @var EntitySyncInterface[]
     */
    private array $entitySyncs;

    /**
     * @var SyncStatusRepositoryInterface
     */
    private SyncStatusRepositoryInterface $syncStatusRepository;

    /**
     * @var SyncStatusInterfaceFactory
     */
    private SyncStatusInterfaceFactory $syncStatusFactory;

    /**
     * @var SyncLogRepositoryInterface
     */
    private SyncLogRepositoryInterface $syncLogRepository;

    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @var SearchCriteriaBuilder
     */
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @param SyncStatusRepositoryInterface $syncStatusRepository
     * @param SyncStatusInterfaceFactory $syncStatusFactory
     * @param SyncLogRepositoryInterface $syncLogRepository
     * @param Config $config
     * @param LoggerInterface $logger
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param array $entitySyncs
     */
    public function __construct(
        SyncStatusRepositoryInterface $syncStatusRepository,
        SyncStatusInterfaceFactory $syncStatusFactory,
        SyncLogRepositoryInterface $syncLogRepository,
        Config $config,
        LoggerInterface $logger,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        array $entitySyncs = []
    ) {
        $this->syncStatusRepository = $syncStatusRepository;
        $this->syncStatusFactory = $syncStatusFactory;
        $this->syncLogRepository = $syncLogRepository;
        $this->config = $config;
        $this->logger = $logger;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->entitySyncs = $entitySyncs;
    }

    /**
     * @inheritDoc
     */
    public function syncAll(): void
    {
        $enabledTypes = $this->config->getEnabledEntityTypes();

        if (empty($enabledTypes)) {
            $enabledTypes = array_keys($this->entitySyncs);
        }

        $sortedTypes = $this->topologicalSort($enabledTypes);

        foreach ($sortedTypes as $entityType) {
            try {
                $this->syncEntity($entityType);
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf('MageClone syncAll failed for entity type "%s": %s', $entityType, $e->getMessage())
                );
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function syncEntity(string $entityType): void
    {
        if (!isset($this->entitySyncs[$entityType])) {
            throw new \InvalidArgumentException(
                sprintf('Unknown entity type: "%s". Available types: %s', $entityType, implode(', ', array_keys($this->entitySyncs)))
            );
        }

        $entitySync = $this->entitySyncs[$entityType];
        $batchSize = $this->config->getBatchSize();

        // Get source count
        $sourceCount = $entitySync->getSourceCount();

        // Update status to running
        $status = $this->getOrCreateStatus($entityType);
        $status->setStatus(SyncStatusInterface::STATUS_RUNNING);
        $status->setSourceCount($sourceCount);
        $status->setSyncedCount(0);
        $status->setFailedCount(0);
        $status->setPendingCount($sourceCount);
        $this->syncStatusRepository->save($status);

        // Calculate total pages
        $totalPages = $batchSize > 0 ? (int) ceil($sourceCount / $batchSize) : 0;

        $totalSynced = 0;
        $totalFailed = 0;

        // Resolve ID mappings for dependencies
        $dependencies = $entitySync->getDependencies();
        $idMappings = [];

        if (!empty($dependencies) && $entitySync instanceof AbstractEntitySync) {
            $idMappings = $entitySync->resolveIdMappings($dependencies);
        }

        // Process each page
        for ($page = 1; $page <= $totalPages; $page++) {
            try {
                $items = $entitySync->fetchPage($page, $batchSize);

                if (empty($items)) {
                    continue;
                }

                $result = $entitySync->saveBatch($items, $idMappings);
                $totalSynced += $result['synced'];
                $totalFailed += $result['failed'];

                // Update progress
                $status->setSyncedCount($totalSynced);
                $status->setFailedCount($totalFailed);
                $status->setPendingCount(max(0, $sourceCount - $totalSynced - $totalFailed));
                $this->syncStatusRepository->save($status);
            } catch (\Exception $e) {
                $this->logger->error(
                    sprintf(
                        'MageClone sync page %d/%d failed for %s: %s',
                        $page,
                        $totalPages,
                        $entityType,
                        $e->getMessage()
                    )
                );
            }
        }

        // Final status update
        $finalStatus = ($totalFailed > 0 && $totalSynced === 0)
            ? SyncStatusInterface::STATUS_FAILED
            : SyncStatusInterface::STATUS_COMPLETED;

        $status->setStatus($finalStatus);
        $status->setSyncedCount($totalSynced);
        $status->setFailedCount($totalFailed);
        $status->setPendingCount(0);
        $status->setLastSyncedAt(date('Y-m-d H:i:s'));
        $this->syncStatusRepository->save($status);

        $this->logger->info(
            sprintf(
                'MageClone sync completed for %s: %d synced, %d failed out of %d total.',
                $entityType,
                $totalSynced,
                $totalFailed,
                $sourceCount
            )
        );
    }

    /**
     * @inheritDoc
     */
    public function getAvailableEntityTypes(): array
    {
        return array_keys($this->entitySyncs);
    }

    /**
     * @inheritDoc
     */
    public function getSyncStatus(): array
    {
        $statuses = [];

        foreach (array_keys($this->entitySyncs) as $entityType) {
            try {
                $statuses[] = $this->syncStatusRepository->getByEntityType($entityType);
            } catch (NoSuchEntityException $e) {
                // No status record exists yet for this entity type
                $status = $this->syncStatusFactory->create();
                $status->setEntityType($entityType);
                $status->setStatus(SyncStatusInterface::STATUS_IDLE);
                $status->setSourceCount(0);
                $status->setDestinationCount(0);
                $status->setSyncedCount(0);
                $status->setFailedCount(0);
                $status->setPendingCount(0);
                $statuses[] = $status;
            }
        }

        return $statuses;
    }

    /**
     * Re-sync failed entities for a specific entity type
     *
     * @param string $entityType
     * @return void
     */
    public function resyncFailed(string $entityType): void
    {
        if (!isset($this->entitySyncs[$entityType])) {
            throw new \InvalidArgumentException(
                sprintf('Unknown entity type: "%s"', $entityType)
            );
        }

        // Query failed sync log entries
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('entity_type', $entityType)
            ->addFilter('status', SyncLogInterface::STATUS_ERROR)
            ->create();

        $failedLogs = $this->syncLogRepository->getList($searchCriteria);

        if ($failedLogs->getTotalCount() === 0) {
            $this->logger->info(sprintf('No failed entries to resync for %s.', $entityType));
            return;
        }

        $entitySync = $this->entitySyncs[$entityType];
        $batchSize = $this->config->getBatchSize();

        // Collect failed source IDs
        $failedSourceIds = [];
        foreach ($failedLogs->getItems() as $log) {
            $sourceId = $log->getSourceId();
            if ($sourceId !== null) {
                $failedSourceIds[] = $sourceId;
            }
        }

        if (empty($failedSourceIds)) {
            return;
        }

        // Re-fetch and re-sync the failed items
        // Fetch all pages and filter for failed source IDs
        $allItems = [];
        $page = 1;

        while (true) {
            $items = $entitySync->fetchPage($page, $batchSize);
            if (empty($items)) {
                break;
            }

            foreach ($items as $item) {
                $itemId = (int) ($item['entity_id'] ?? $item['page_id'] ?? $item['block_id'] ?? 0);
                if (in_array($itemId, $failedSourceIds, true)) {
                    $allItems[] = $item;
                }
            }

            if (count($allItems) >= count($failedSourceIds)) {
                break;
            }

            $page++;
        }

        if (!empty($allItems)) {
            $dependencies = $entitySync->getDependencies();
            $idMappings = [];
            if (!empty($dependencies) && $entitySync instanceof AbstractEntitySync) {
                $idMappings = $entitySync->resolveIdMappings($dependencies);
            }

            $result = $entitySync->saveBatch($allItems, $idMappings);

            $this->logger->info(
                sprintf(
                    'MageClone resync completed for %s: %d synced, %d failed.',
                    $entityType,
                    $result['synced'],
                    $result['failed']
                )
            );
        }
    }

    /**
     * Perform topological sort of entity types based on their dependencies
     *
     * @param string[] $entityTypes
     * @return string[]
     */
    private function topologicalSort(array $entityTypes): array
    {
        $graph = [];
        $inDegree = [];

        // Build adjacency list and in-degree count
        foreach ($entityTypes as $type) {
            if (!isset($graph[$type])) {
                $graph[$type] = [];
            }
            if (!isset($inDegree[$type])) {
                $inDegree[$type] = 0;
            }

            if (isset($this->entitySyncs[$type])) {
                $deps = $this->entitySyncs[$type]->getDependencies();
                foreach ($deps as $dep) {
                    if (in_array($dep, $entityTypes, true)) {
                        $graph[$dep][] = $type;
                        $inDegree[$type]++;
                        if (!isset($inDegree[$dep])) {
                            $inDegree[$dep] = 0;
                        }
                    }
                }
            }
        }

        // Kahn's algorithm
        $queue = [];
        foreach ($inDegree as $node => $degree) {
            if ($degree === 0) {
                $queue[] = $node;
            }
        }

        $sorted = [];
        while (!empty($queue)) {
            $current = array_shift($queue);
            $sorted[] = $current;

            foreach ($graph[$current] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue[] = $neighbor;
                }
            }
        }

        // If sorted count does not match, there is a circular dependency; append remaining
        if (count($sorted) < count($entityTypes)) {
            foreach ($entityTypes as $type) {
                if (!in_array($type, $sorted, true)) {
                    $sorted[] = $type;
                }
            }
        }

        return $sorted;
    }

    /**
     * Get or create a sync status record for the given entity type
     *
     * @param string $entityType
     * @return SyncStatusInterface
     */
    private function getOrCreateStatus(string $entityType): SyncStatusInterface
    {
        try {
            return $this->syncStatusRepository->getByEntityType($entityType);
        } catch (NoSuchEntityException $e) {
            $status = $this->syncStatusFactory->create();
            $status->setEntityType($entityType);
            return $status;
        }
    }
}
