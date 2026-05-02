<?php
/**
 * MageClone MagentoMigrator Sync Status Repository
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Repository;

use MageClone\MagentoMigrator\Api\Data\SyncStatusInterface;
use MageClone\MagentoMigrator\Api\SyncStatusRepositoryInterface;
use MageClone\MagentoMigrator\Model\ResourceModel\SyncStatus as SyncStatusResource;
use MageClone\MagentoMigrator\Model\ResourceModel\SyncStatus\CollectionFactory as SyncStatusCollectionFactory;
use MageClone\MagentoMigrator\Model\SyncStatusFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class SyncStatusRepository
 *
 * Repository implementation for sync status records.
 */
class SyncStatusRepository implements SyncStatusRepositoryInterface
{
    /**
     * @var SyncStatusResource
     */
    private SyncStatusResource $resource;

    /**
     * @var SyncStatusFactory
     */
    private SyncStatusFactory $syncStatusFactory;

    /**
     * @var SyncStatusCollectionFactory
     */
    private SyncStatusCollectionFactory $collectionFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private CollectionProcessorInterface $collectionProcessor;

    /**
     * @var SearchResultsInterfaceFactory
     */
    private SearchResultsInterfaceFactory $searchResultsFactory;

    /**
     * @param SyncStatusResource $resource
     * @param SyncStatusFactory $syncStatusFactory
     * @param SyncStatusCollectionFactory $collectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     */
    public function __construct(
        SyncStatusResource $resource,
        SyncStatusFactory $syncStatusFactory,
        SyncStatusCollectionFactory $collectionFactory,
        CollectionProcessorInterface $collectionProcessor,
        SearchResultsInterfaceFactory $searchResultsFactory
    ) {
        $this->resource = $resource;
        $this->syncStatusFactory = $syncStatusFactory;
        $this->collectionFactory = $collectionFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    /**
     * @inheritdoc
     */
    public function save(SyncStatusInterface $syncStatus): SyncStatusInterface
    {
        try {
            /** @var \MageClone\MagentoMigrator\Model\SyncStatus $syncStatus */
            $this->resource->save($syncStatus);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the sync status: %1', $exception->getMessage()),
                $exception
            );
        }

        return $syncStatus;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $statusId): SyncStatusInterface
    {
        $syncStatus = $this->syncStatusFactory->create();
        $this->resource->load($syncStatus, $statusId);

        if (!$syncStatus->getStatusId()) {
            throw new NoSuchEntityException(
                __('Sync status with ID "%1" does not exist.', $statusId)
            );
        }

        return $syncStatus;
    }

    /**
     * @inheritdoc
     */
    public function getByEntityType(string $entityType): SyncStatusInterface
    {
        $syncStatus = $this->syncStatusFactory->create();
        $this->resource->load($syncStatus, $entityType, SyncStatusInterface::ENTITY_TYPE);

        if (!$syncStatus->getStatusId()) {
            throw new NoSuchEntityException(
                __('Sync status for entity type "%1" does not exist.', $entityType)
            );
        }

        return $syncStatus;
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
    public function delete(SyncStatusInterface $syncStatus): bool
    {
        try {
            /** @var \MageClone\MagentoMigrator\Model\SyncStatus $syncStatus */
            $this->resource->delete($syncStatus);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the sync status: %1', $exception->getMessage()),
                $exception
            );
        }

        return true;
    }
}
