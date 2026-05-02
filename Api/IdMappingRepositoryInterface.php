<?php
/**
 * MageClone MagentoMigrator ID Mapping Repository Interface
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Api;

use MageClone\MagentoMigrator\Api\Data\IdMappingInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Interface IdMappingRepositoryInterface
 *
 * Repository for managing entity ID mappings between source and destination.
 */
interface IdMappingRepositoryInterface
{
    /**
     * Save ID mapping record
     *
     * @param IdMappingInterface $idMapping
     * @return IdMappingInterface
     * @throws CouldNotSaveException
     */
    public function save(IdMappingInterface $idMapping): IdMappingInterface;

    /**
     * Get ID mapping by ID
     *
     * @param int $mappingId
     * @return IdMappingInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $mappingId): IdMappingInterface;

    /**
     * Get ID mapping by source entity type and source ID
     *
     * @param string $entityType
     * @param int $sourceId
     * @return IdMappingInterface
     * @throws NoSuchEntityException
     */
    public function getBySourceId(string $entityType, int $sourceId): IdMappingInterface;

    /**
     * Get ID mapping by entity type and destination ID
     *
     * @param string $entityType
     * @param int $destinationId
     * @return IdMappingInterface
     * @throws NoSuchEntityException
     */
    public function getByDestinationId(string $entityType, int $destinationId): IdMappingInterface;

    /**
     * Get list of ID mappings matching search criteria
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * Delete ID mapping record
     *
     * @param IdMappingInterface $idMapping
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(IdMappingInterface $idMapping): bool;
}
