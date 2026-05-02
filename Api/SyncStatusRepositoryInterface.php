<?php
/**
 * MageClone MagentoMigrator Sync Status Repository Interface
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Api;

use MageClone\MagentoMigrator\Api\Data\SyncStatusInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Interface SyncStatusRepositoryInterface
 *
 * Repository for managing sync status records.
 */
interface SyncStatusRepositoryInterface
{
    /**
     * Save sync status record
     *
     * @param SyncStatusInterface $syncStatus
     * @return SyncStatusInterface
     * @throws CouldNotSaveException
     */
    public function save(SyncStatusInterface $syncStatus): SyncStatusInterface;

    /**
     * Get sync status by ID
     *
     * @param int $statusId
     * @return SyncStatusInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $statusId): SyncStatusInterface;

    /**
     * Get sync status by entity type
     *
     * @param string $entityType
     * @return SyncStatusInterface
     * @throws NoSuchEntityException
     */
    public function getByEntityType(string $entityType): SyncStatusInterface;

    /**
     * Get list of sync statuses matching search criteria
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * Delete sync status record
     *
     * @param SyncStatusInterface $syncStatus
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(SyncStatusInterface $syncStatus): bool;
}
