<?php
/**
 * MageClone MagentoMigrator Sync Log Data Interface
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Api\Data;

/**
 * Interface SyncLogInterface
 *
 * Represents a single sync log entry.
 */
interface SyncLogInterface
{
    /**
     * Table name constant
     */
    public const TABLE_NAME = 'mageclone_sync_log';

    /**
     * Column name constants
     */
    public const LOG_ID = 'log_id';
    public const ENTITY_TYPE = 'entity_type';
    public const SOURCE_ID = 'source_id';
    public const STATUS = 'status';
    public const MESSAGE = 'message';
    public const BATCH_ID = 'batch_id';
    public const CREATED_AT = 'created_at';

    /**
     * Log status constants
     */
    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_INFO = 'info';

    /**
     * Get log ID
     *
     * @return int|null
     */
    public function getLogId(): ?int;

    /**
     * Set log ID
     *
     * @param int $logId
     * @return $this
     */
    public function setLogId(int $logId): self;

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
     * Get source ID
     *
     * @return int|null
     */
    public function getSourceId(): ?int;

    /**
     * Set source ID
     *
     * @param int|null $sourceId
     * @return $this
     */
    public function setSourceId(?int $sourceId): self;

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
     * Get message
     *
     * @return string|null
     */
    public function getMessage(): ?string;

    /**
     * Set message
     *
     * @param string|null $message
     * @return $this
     */
    public function setMessage(?string $message): self;

    /**
     * Get batch ID
     *
     * @return string|null
     */
    public function getBatchId(): ?string;

    /**
     * Set batch ID
     *
     * @param string|null $batchId
     * @return $this
     */
    public function setBatchId(?string $batchId): self;

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
}
