<?php
/**
 * MageClone MagentoMigrator Sync Status Data Interface
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Api\Data;

/**
 * Interface SyncStatusInterface
 *
 * Represents the synchronization status for a given entity type.
 */
interface SyncStatusInterface
{
    /**
     * Table name constant
     */
    public const TABLE_NAME = 'mageclone_sync_status';

    /**
     * Column name constants
     */
    public const STATUS_ID = 'status_id';
    public const ENTITY_TYPE = 'entity_type';
    public const SOURCE_COUNT = 'source_count';
    public const DESTINATION_COUNT = 'destination_count';
    public const SYNCED_COUNT = 'synced_count';
    public const FAILED_COUNT = 'failed_count';
    public const PENDING_COUNT = 'pending_count';
    public const STATUS = 'status';
    public const LAST_SYNCED_AT = 'last_synced_at';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * Status value constants
     */
    public const STATUS_IDLE = 'idle';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /**
     * Get status ID
     *
     * @return int|null
     */
    public function getStatusId(): ?int;

    /**
     * Set status ID
     *
     * @param int $statusId
     * @return $this
     */
    public function setStatusId(int $statusId): self;

    /**
     * Get entity type
     *
     * @return string|null
     */
    public function getEntityType(): ?string;

    /**
     * Set entity type
     *
     * @param string $entityType
     * @return $this
     */
    public function setEntityType(string $entityType): self;

    /**
     * Get source count
     *
     * @return int
     */
    public function getSourceCount(): int;

    /**
     * Set source count
     *
     * @param int $sourceCount
     * @return $this
     */
    public function setSourceCount(int $sourceCount): self;

    /**
     * Get destination count
     *
     * @return int
     */
    public function getDestinationCount(): int;

    /**
     * Set destination count
     *
     * @param int $destinationCount
     * @return $this
     */
    public function setDestinationCount(int $destinationCount): self;

    /**
     * Get synced count
     *
     * @return int
     */
    public function getSyncedCount(): int;

    /**
     * Set synced count
     *
     * @param int $syncedCount
     * @return $this
     */
    public function setSyncedCount(int $syncedCount): self;

    /**
     * Get failed count
     *
     * @return int
     */
    public function getFailedCount(): int;

    /**
     * Set failed count
     *
     * @param int $failedCount
     * @return $this
     */
    public function setFailedCount(int $failedCount): self;

    /**
     * Get pending count
     *
     * @return int
     */
    public function getPendingCount(): int;

    /**
     * Set pending count
     *
     * @param int $pendingCount
     * @return $this
     */
    public function setPendingCount(int $pendingCount): self;

    /**
     * Get status
     *
     * @return string|null
     */
    public function getStatus(): ?string;

    /**
     * Set status
     *
     * @param string $status
     * @return $this
     */
    public function setStatus(string $status): self;

    /**
     * Get last synced at timestamp
     *
     * @return string|null
     */
    public function getLastSyncedAt(): ?string;

    /**
     * Set last synced at timestamp
     *
     * @param string|null $lastSyncedAt
     * @return $this
     */
    public function setLastSyncedAt(?string $lastSyncedAt): self;

    /**
     * Get created at timestamp
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Set created at timestamp
     *
     * @param string $createdAt
     * @return $this
     */
    public function setCreatedAt(string $createdAt): self;

    /**
     * Get updated at timestamp
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;

    /**
     * Set updated at timestamp
     *
     * @param string $updatedAt
     * @return $this
     */
    public function setUpdatedAt(string $updatedAt): self;
}
