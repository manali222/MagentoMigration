<?php
/**
 * MageClone MagentoMigrator Abstract Entity Sync
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Sync;

use MageClone\MagentoMigrator\Api\GraphQlClientInterface;
use MageClone\MagentoMigrator\Api\IdMappingRepositoryInterface;
use MageClone\MagentoMigrator\Api\SyncLogRepositoryInterface;
use MageClone\MagentoMigrator\Api\Data\SyncLogInterfaceFactory;
use MageClone\MagentoMigrator\Api\Data\IdMappingInterfaceFactory;
use MageClone\MagentoMigrator\Api\Data\SyncLogInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for entity synchronization handlers.
 *
 * Provides shared infrastructure for fetching from source via GraphQL,
 * saving to destination, and logging results.
 */
abstract class AbstractEntitySync implements EntitySyncInterface
{
    /**
     * @var GraphQlClientInterface
     */
    protected GraphQlClientInterface $graphQlClient;

    /**
     * @var IdMappingRepositoryInterface
     */
    protected IdMappingRepositoryInterface $idMappingRepository;

    /**
     * @var SyncLogRepositoryInterface
     */
    protected SyncLogRepositoryInterface $syncLogRepository;

    /**
     * @var SyncLogInterfaceFactory
     */
    protected SyncLogInterfaceFactory $syncLogFactory;

    /**
     * @var IdMappingInterfaceFactory
     */
    protected IdMappingInterfaceFactory $idMappingFactory;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var SearchCriteriaBuilder
     */
    protected SearchCriteriaBuilder $searchCriteriaBuilder;

    /**
     * @param GraphQlClientInterface $graphQlClient
     * @param IdMappingRepositoryInterface $idMappingRepository
     * @param SyncLogRepositoryInterface $syncLogRepository
     * @param SyncLogInterfaceFactory $syncLogFactory
     * @param IdMappingInterfaceFactory $idMappingFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        GraphQlClientInterface $graphQlClient,
        IdMappingRepositoryInterface $idMappingRepository,
        SyncLogRepositoryInterface $syncLogRepository,
        SyncLogInterfaceFactory $syncLogFactory,
        IdMappingInterfaceFactory $idMappingFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger
    ) {
        $this->graphQlClient = $graphQlClient;
        $this->idMappingRepository = $idMappingRepository;
        $this->syncLogRepository = $syncLogRepository;
        $this->syncLogFactory = $syncLogFactory;
        $this->idMappingFactory = $idMappingFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->logger = $logger;
    }

    /**
     * @inheritDoc
     */
    public function fetchPage(int $page, int $pageSize, ?string $updatedSince = null): array
    {
        $variables = [
            'pageSize' => $pageSize,
            'currentPage' => $page,
        ];

        if ($updatedSince !== null) {
            $variables['updatedSince'] = $updatedSince;
        }

        $query = $this->getGraphQlQuery();
        $data = $this->graphQlClient->query($query, $variables);
        $responseKey = $this->getResponseKey();

        if (!isset($data[$responseKey]['items'])) {
            return [];
        }

        return $data[$responseKey]['items'];
    }

    /**
     * @inheritDoc
     */
    public function getSourceCount(?string $updatedSince = null): int
    {
        $variables = [
            'pageSize' => 1,
            'currentPage' => 1,
        ];

        if ($updatedSince !== null) {
            $variables['updatedSince'] = $updatedSince;
        }

        $query = $this->getGraphQlQuery();
        $data = $this->graphQlClient->query($query, $variables);
        $responseKey = $this->getResponseKey();

        return (int) ($data[$responseKey]['total_count'] ?? 0);
    }

    /**
     * @inheritDoc
     */
    public function saveBatch(array $items, array $idMappings = []): array
    {
        $synced = 0;
        $failed = 0;
        $errors = [];
        $batchId = uniqid('batch_', true);

        foreach ($items as $item) {
            $sourceId = (int) ($item['entity_id'] ?? $item['page_id'] ?? $item['block_id'] ?? 0);

            try {
                $destinationId = $this->saveEntity($item);
                $this->logSuccess($this->getEntityType(), $sourceId, $destinationId, $batchId);
                $this->createOrUpdateIdMapping($sourceId, $destinationId);
                $synced++;
            } catch (\Exception $e) {
                $errorMessage = $e->getMessage();
                $this->logFailure($this->getEntityType(), $sourceId, $errorMessage, $batchId);
                $errors[] = [
                    'source_id' => $sourceId,
                    'message' => $errorMessage,
                ];
                $failed++;

                $this->logger->error(
                    sprintf(
                        'MageClone sync failed for %s (source ID: %d): %s',
                        $this->getEntityType(),
                        $sourceId,
                        $errorMessage
                    )
                );
            }
        }

        return [
            'synced' => $synced,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Log a successful sync operation
     *
     * @param string $entityType
     * @param int $sourceId
     * @param int $destinationId
     * @param string $batchId
     * @return void
     */
    protected function logSuccess(string $entityType, int $sourceId, int $destinationId, string $batchId): void
    {
        try {
            $syncLog = $this->syncLogFactory->create();
            $syncLog->setEntityType($entityType);
            $syncLog->setSourceId($sourceId);
            $syncLog->setStatus(SyncLogInterface::STATUS_SUCCESS);
            $syncLog->setMessage(sprintf('Mapped to destination ID: %d', $destinationId));
            $syncLog->setBatchId($batchId);
            $this->syncLogRepository->save($syncLog);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to save sync success log: ' . $e->getMessage());
        }
    }

    /**
     * Log a failed sync operation
     *
     * @param string $entityType
     * @param int $sourceId
     * @param string $message
     * @param string $batchId
     * @return void
     */
    protected function logFailure(string $entityType, int $sourceId, string $message, string $batchId): void
    {
        try {
            $syncLog = $this->syncLogFactory->create();
            $syncLog->setEntityType($entityType);
            $syncLog->setSourceId($sourceId);
            $syncLog->setStatus(SyncLogInterface::STATUS_ERROR);
            $syncLog->setMessage($message);
            $syncLog->setBatchId($batchId);
            $this->syncLogRepository->save($syncLog);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to save sync failure log: ' . $e->getMessage());
        }
    }

    /**
     * Load all ID mappings for the given entity types
     *
     * @param string[] $entityTypes
     * @return array Keyed array: [entityType => [sourceId => destinationId, ...], ...]
     */
    protected function resolveIdMappings(array $entityTypes): array
    {
        $mappings = [];

        foreach ($entityTypes as $entityType) {
            $mappings[$entityType] = [];

            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('entity_type', $entityType)
                ->create();

            $results = $this->idMappingRepository->getList($searchCriteria);

            foreach ($results->getItems() as $mapping) {
                $mappings[$entityType][$mapping->getSourceId()] = $mapping->getDestinationId();
            }
        }

        return $mappings;
    }

    /**
     * Create or update an ID mapping record
     *
     * @param int $sourceId
     * @param int $destinationId
     * @return void
     */
    private function createOrUpdateIdMapping(int $sourceId, int $destinationId): void
    {
        try {
            $mapping = $this->idMappingRepository->getBySourceId($this->getEntityType(), $sourceId);
            $mapping->setDestinationId($destinationId);
        } catch (NoSuchEntityException $e) {
            $mapping = $this->idMappingFactory->create();
            $mapping->setEntityType($this->getEntityType());
            $mapping->setSourceId($sourceId);
            $mapping->setDestinationId($destinationId);
        }

        try {
            $this->idMappingRepository->save($mapping);
        } catch (\Exception $e) {
            $this->logger->error('Failed to save ID mapping: ' . $e->getMessage());
        }
    }

    /**
     * Get the GraphQL query string for fetching entities
     *
     * @return string
     */
    abstract protected function getGraphQlQuery(): string;

    /**
     * Get the response key that contains the entity data in the GraphQL response
     *
     * @return string
     */
    abstract protected function getResponseKey(): string;

    /**
     * Save a single entity to the destination instance
     *
     * @param array $entityData The mapped entity data
     * @return int The destination entity ID
     * @throws \Exception If saving fails
     */
    abstract protected function saveEntity(array $entityData): int;
}
