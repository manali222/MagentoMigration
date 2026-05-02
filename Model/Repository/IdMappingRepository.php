<?php
/**
 * MageClone MagentoMigrator ID Mapping Repository
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Repository;

use MageClone\MagentoMigrator\Api\Data\IdMappingInterface;
use MageClone\MagentoMigrator\Api\IdMappingRepositoryInterface;
use MageClone\MagentoMigrator\Model\ResourceModel\IdMapping as IdMappingResource;
use MageClone\MagentoMigrator\Model\ResourceModel\IdMapping\CollectionFactory as IdMappingCollectionFactory;
use MageClone\MagentoMigrator\Model\IdMappingFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class IdMappingRepository
 *
 * Repository implementation for entity ID mapping records.
 */
class IdMappingRepository implements IdMappingRepositoryInterface
{
    /**
     * @var IdMappingResource
     */
    private IdMappingResource $resource;

    /**
     * @var IdMappingFactory
     */
    private IdMappingFactory $idMappingFactory;

    /**
     * @var IdMappingCollectionFactory
     */
    private IdMappingCollectionFactory $collectionFactory;

    /**
     * @var CollectionProcessorInterface
     */
    private CollectionProcessorInterface $collectionProcessor;

    /**
     * @var SearchResultsInterfaceFactory
     */
    private SearchResultsInterfaceFactory $searchResultsFactory;

    /**
     * @param IdMappingResource $resource
     * @param IdMappingFactory $idMappingFactory
     * @param IdMappingCollectionFactory $collectionFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     */
    public function __construct(
        IdMappingResource $resource,
        IdMappingFactory $idMappingFactory,
        IdMappingCollectionFactory $collectionFactory,
        CollectionProcessorInterface $collectionProcessor,
        SearchResultsInterfaceFactory $searchResultsFactory
    ) {
        $this->resource = $resource;
        $this->idMappingFactory = $idMappingFactory;
        $this->collectionFactory = $collectionFactory;
        $this->collectionProcessor = $collectionProcessor;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    /**
     * @inheritdoc
     */
    public function save(IdMappingInterface $idMapping): IdMappingInterface
    {
        try {
            /** @var \MageClone\MagentoMigrator\Model\IdMapping $idMapping */
            $this->resource->save($idMapping);
        } catch (\Exception $exception) {
            throw new CouldNotSaveException(
                __('Could not save the ID mapping: %1', $exception->getMessage()),
                $exception
            );
        }

        return $idMapping;
    }

    /**
     * @inheritdoc
     */
    public function getById(int $mappingId): IdMappingInterface
    {
        $idMapping = $this->idMappingFactory->create();
        $this->resource->load($idMapping, $mappingId);

        if (!$idMapping->getMappingId()) {
            throw new NoSuchEntityException(
                __('ID mapping with ID "%1" does not exist.', $mappingId)
            );
        }

        return $idMapping;
    }

    /**
     * @inheritdoc
     */
    public function getBySourceId(string $entityType, int $sourceId): IdMappingInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(IdMappingInterface::ENTITY_TYPE, $entityType);
        $collection->addFieldToFilter(IdMappingInterface::SOURCE_ID, $sourceId);
        $collection->setPageSize(1);

        /** @var \MageClone\MagentoMigrator\Model\IdMapping|null $idMapping */
        $idMapping = $collection->getFirstItem();

        if (!$idMapping || !$idMapping->getMappingId()) {
            throw new NoSuchEntityException(
                __(
                    'ID mapping for entity type "%1" and source ID "%2" does not exist.',
                    $entityType,
                    $sourceId
                )
            );
        }

        return $idMapping;
    }

    /**
     * @inheritdoc
     */
    public function getByDestinationId(string $entityType, int $destinationId): IdMappingInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(IdMappingInterface::ENTITY_TYPE, $entityType);
        $collection->addFieldToFilter(IdMappingInterface::DESTINATION_ID, $destinationId);
        $collection->setPageSize(1);

        /** @var \MageClone\MagentoMigrator\Model\IdMapping|null $idMapping */
        $idMapping = $collection->getFirstItem();

        if (!$idMapping || !$idMapping->getMappingId()) {
            throw new NoSuchEntityException(
                __(
                    'ID mapping for entity type "%1" and destination ID "%2" does not exist.',
                    $entityType,
                    $destinationId
                )
            );
        }

        return $idMapping;
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
    public function delete(IdMappingInterface $idMapping): bool
    {
        try {
            /** @var \MageClone\MagentoMigrator\Model\IdMapping $idMapping */
            $this->resource->delete($idMapping);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(
                __('Could not delete the ID mapping: %1', $exception->getMessage()),
                $exception
            );
        }

        return true;
    }
}
