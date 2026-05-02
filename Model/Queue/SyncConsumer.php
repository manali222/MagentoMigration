<?php
/**
 * MageClone MagentoMigrator Sync Queue Consumer
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Queue;

use MageClone\MagentoMigrator\Api\Data\SyncStatusInterface;
use MageClone\MagentoMigrator\Api\SyncStatusRepositoryInterface;
use MageClone\MagentoMigrator\Model\Config;
use MageClone\MagentoMigrator\Model\SyncStatusFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Class SyncConsumer
 *
 * Processes sync messages from the message queue.
 */
class SyncConsumer
{
    /**
     * @param array $entitySyncs
     * @param SyncStatusRepositoryInterface $syncStatusRepository
     * @param SyncStatusFactory $syncStatusFactory
     * @param LoggerInterface $logger
     * @param Config $config
     */
    public function __construct(
        private readonly array $entitySyncs,
        private readonly SyncStatusRepositoryInterface $syncStatusRepository,
        private readonly SyncStatusFactory $syncStatusFactory,
        private readonly LoggerInterface $logger,
        private readonly Config $config
    ) {
    }

    /**
     * Process a sync message from the queue
     *
     * @param SyncMessage $message
     * @return void
     */
    public function process(SyncMessage $message): void
    {
        $entityType = $message->getEntityType();
        $page = $message->getPage();
        $pageSize = $message->getPageSize();
        $batchId = $message->getBatchId();
        $updatedSince = $message->getUpdatedSince();

        $this->logger->info(
            sprintf(
                'MageClone: Processing sync queue message for entity "%s", page %d, batch "%s".',
                $entityType,
                $page,
                $batchId
            )
        );

        try {
            $entitySync = $this->getEntitySync($entityType);
            if ($entitySync === null) {
                $this->logger->error(
                    sprintf('MageClone: No entity sync handler registered for type "%s".', $entityType)
                );
                $this->updateStatusOnFailure(
                    $entityType,
                    sprintf('No entity sync handler registered for type "%s".', $entityType)
                );
                return;
            }

            $items = $entitySync->fetchPage($page, $pageSize, $updatedSince);

            $idMappings = $entitySync->resolveIdMappings();

            $result = $entitySync->saveBatch($items, $idMappings);

            $syncedCount = (int) ($result['synced'] ?? 0);
            $failedCount = (int) ($result['failed'] ?? 0);

            $this->updateStatusCounters($entityType, $syncedCount, $failedCount);

            $syncStatus = $this->getOrCreateSyncStatus($entityType);
            $totalProcessed = $syncStatus->getSyncedCount() + $syncStatus->getFailedCount();
            $sourceCount = $syncStatus->getSourceCount();

            if ($sourceCount > 0 && $totalProcessed >= $sourceCount) {
                $finalStatus = $syncStatus->getFailedCount() > 0
                    ? 'completed_with_errors'
                    : SyncStatusInterface::STATUS_COMPLETED;
                $syncStatus->setStatus($finalStatus);
                $syncStatus->setLastSyncedAt(date('Y-m-d H:i:s'));
                $this->syncStatusRepository->save($syncStatus);
            }

            if ($failedCount > 0) {
                $this->logger->warning(
                    sprintf(
                        'MageClone: Batch "%s" for entity "%s" page %d completed with %d synced, %d failed.',
                        $batchId,
                        $entityType,
                        $page,
                        $syncedCount,
                        $failedCount
                    )
                );
            } else {
                $this->logger->info(
                    sprintf(
                        'MageClone: Batch "%s" for entity "%s" page %d completed successfully. %d records synced.',
                        $batchId,
                        $entityType,
                        $page,
                        $syncedCount
                    )
                );
            }
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf(
                    'MageClone: Failed to process sync message for entity "%s", page %d, batch "%s". Error: %s',
                    $entityType,
                    $page,
                    $batchId,
                    $e->getMessage()
                ),
                ['exception' => $e]
            );
            $this->updateStatusOnFailure($entityType, $e->getMessage());
        }
    }

    /**
     * Get the entity sync handler for the given entity type
     *
     * @param string $entityType
     * @return object|null
     */
    private function getEntitySync(string $entityType): ?object
    {
        return $this->entitySyncs[$entityType] ?? null;
    }

    /**
     * Update sync status counters after processing a batch
     *
     * @param string $entityType
     * @param int $syncedCount
     * @param int $failedCount
     * @return void
     */
    private function updateStatusCounters(string $entityType, int $syncedCount, int $failedCount): void
    {
        try {
            $syncStatus = $this->getOrCreateSyncStatus($entityType);
            $syncStatus->setSyncedCount($syncStatus->getSyncedCount() + $syncedCount);
            $syncStatus->setFailedCount($syncStatus->getFailedCount() + $failedCount);
            $syncStatus->setPendingCount(
                max(0, $syncStatus->getPendingCount() - $syncedCount - $failedCount)
            );
            $this->syncStatusRepository->save($syncStatus);
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('MageClone: Failed to update sync status counters for "%s": %s', $entityType, $e->getMessage())
            );
        }
    }

    /**
     * Update sync status to failed
     *
     * @param string $entityType
     * @param string $errorMessage
     * @return void
     */
    private function updateStatusOnFailure(string $entityType, string $errorMessage): void
    {
        try {
            $syncStatus = $this->getOrCreateSyncStatus($entityType);
            $syncStatus->setStatus(SyncStatusInterface::STATUS_FAILED);
            $this->syncStatusRepository->save($syncStatus);
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf(
                    'MageClone: Failed to update sync status to failed for "%s": %s',
                    $entityType,
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Get or create a sync status record for the given entity type
     *
     * @param string $entityType
     * @return SyncStatusInterface
     */
    private function getOrCreateSyncStatus(string $entityType): SyncStatusInterface
    {
        try {
            return $this->syncStatusRepository->getByEntityType($entityType);
        } catch (NoSuchEntityException $e) {
            $syncStatus = $this->syncStatusFactory->create();
            $syncStatus->setEntityType($entityType);
            $syncStatus->setStatus(SyncStatusInterface::STATUS_IDLE);
            $syncStatus->setSyncedCount(0);
            $syncStatus->setFailedCount(0);
            $syncStatus->setPendingCount(0);
            $syncStatus->setSourceCount(0);
            $syncStatus->setDestinationCount(0);
            return $this->syncStatusRepository->save($syncStatus);
        }
    }
}
