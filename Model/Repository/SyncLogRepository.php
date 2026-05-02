<?php
/**
 * MageClone MagentoMigrator Sync Log Repository
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Repository;

use MageClone\MagentoMigrator\Api\Data\SyncLogInterface;
use MageClone\MagentoMigrator\Api\SyncLogRepositoryInterface;
use MageClone\MagentoMigrator\Model\ResourceModel\SyncLog as SyncLogResource;
use MageClone\MagentoMigrator\Model\ResourceModel\SyncLog\CollectionFactory as SyncLogCollectionFactory;
use MageClone\MagentoMigrator\Model\SyncLogFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class SyncLogRepository
 *
 * Repository implementation for sync log records.
 */
class SyncLogRepository implements SyncLogRepositoryInterface
{
    /**
     * @var SyncLogResource
     */
    private SyncLogResource $resource;

    /**
     * @var SyncLogFactory
     */
    private SyncLogFactory $syncLogFactory;

    /**
     * @var SyncLogCollectionFactory
     */
    private SyncLogCollectionFactory $collectionFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private CollectionProcessorInterface $collectionProcessor;

    /**
     * @var SearchResultsInterfaceFactory
     */
    private SearchResultsInterfaceFactory $searchResultsFactory;

    /**
     * @param SyncLogResource $resource
     * @param SyncLogFactory $syncLogFactory
     * @param SyncLogCollectionFactory $collectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     */
    public function __construct(
        SyncLogResource $resource,
        SyncLogFactory $syncLogFactory,
        SyncLogCollectionFactory $collectionFactory,
        CollectionProcessorInterface $collectionProcessor,
        SearchResultsInterfaceFactory $searchResultsFactory
    ) {
        $this->resource = $resource;
        $this->syncLogFactory = $syncLogFactory;
        $this->collectionFactory = $collectionFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    /**
     * @inheritdoc
     */
    public function save(SyncLogInterface $syncLog): SyncLogInterface
    {
        try {
            /** @var \MageClone\MagentoMigrator\Model\SyncLog $syncLog */
            $this->resource->save($syncLog);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the sync log: %1', $exception->getMessage()),
                $exception
            );
        }

        return $syncLog;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $logId): SyncLogInterface
    {
        $syncLog = $this->syncLogFactory->create();
        $this->resource->load($syncLog, $logId);

        if (!$syncLog->getLogId()) {
            throw new NoSuchEntityException(
                __('Sync log with ID "%1" does not exist.', $logId)
            );
        }

        return $syncLog;
    }

    /**
     * @inheritdoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @inheritdoc
     */
    public function deleteOlderThan(string $date): int
    {
        try {
            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter(
                SyncLogInterface::CREATED_AT,
                ['lt' => $date]
            );

            $deletedCount = 0;
            foreach ($collection as $syncLog) {
                /** @var \MageClone\MagentoMigrator\Model\SyncLog $syncLog */
                $this->resource->delete($syncLog);
                $deletedCount++;
            }

            return $deletedCount;
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete old sync log entries: %1', $exception->getMessage()),
                $exception
            );
        }
    }
}
