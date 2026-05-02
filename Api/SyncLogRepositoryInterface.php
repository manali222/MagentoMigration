<?php
/**
 * MageClone MagentoMigrator Sync Log Repository Interface
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Api;

use MageClone\MagentoMigrator\Api\Data\SyncLogInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Interface SyncLogRepositoryInterface
 *
 * Repository for managing sync log records.
 */
interface SyncLogRepositoryInterface
{
    /**
     * Save sync log record
     *
     * @param SyncLogInterface $syncLog
     * @return SyncLogInterface
     * @throws CouldNotSaveException
     */
    public function save(SyncLogInterface $syncLog): SyncLogInterface;

    /**
     * Get sync log by ID
     *
     * @param int $logId
     * @return SyncLogInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $logId): SyncLogInterface;

    /**
     * Get list of sync logs matching search criteria
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;

    /**
     * Delete log entries older than the specified date
     *
     * @param string $date Date string in Y-m-d H:i:s format
     * @return int Number of deleted records
     * @throws CouldNotDeleteException
     */
    public function deleteOlderThan(string $date): int;
}
