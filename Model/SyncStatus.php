<?php
/**
 * MageClone MagentoMigrator Sync Status Model
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model;

use MageClone\MagentoMigrator\Api\Data\SyncStatusInterface;
use MageClone\MagentoMigrator\Model\ResourceModel\SyncStatus as SyncStatusResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Class SyncStatus
 *
 * Data model for sync status records.
 */
class SyncStatus extends AbstractModel implements SyncStatusInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'mageclone_sync_status';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(SyncStatusResource::class);
    }

    /**
     * @inheritdoc
     */
    public function getStatusId(): ?int
    {
        $value = $this->getData(self::STATUS_ID);
        return $value !== null ? (int) $value : null;
    }

    /**
     * @inheritdoc
     */
    public function setStatusId(int $statusId): SyncStatusInterface
    {
        return $this->setData(self::STATUS_ID, $statusId);
    }

    /**
     * @inheritdoc
     */
    public function getEntityType(): ?string
    {
        return $this->getData(self::ENTITY_TYPE);
    }

    /**
     * @inheritdoc
     */
    public function setEntityType(string $entityType): SyncStatusInterface
    {
        return $this->setData(self::ENTITY_TYPE, $entityType);
    }

    /**
     * @inheritdoc
     */
    public function getSourceCount(): int
    {
        return (int) $this->getData(self::SOURCE_COUNT);
    }

    /**
     * @inheritdoc
     */
    public function setSourceCount(int $sourceCount): SyncStatusInterface
    {
        return $this->setData(self::SOURCE_COUNT, $sourceCount);
    }

    /**
     * @inheritdoc
     */
    public function getDestinationCount(): int
    {
        return (int) $this->getData(self::DESTINATION_COUNT);
    }

    /**
     * @inheritdoc
     */
    public function setDestinationCount(int $destinationCount): SyncStatusInterface
    {
        return $this->setData(self::DESTINATION_COUNT, $destinationCount);
    }

    /**
     * @inheritdoc
     */
    public function getSyncedCount(): int
    {
        return (int) $this->getData(self::SYNCED_COUNT);
    }

    /**
     * @inheritdoc
     */
    public function setSyncedCount(int $syncedCount): SyncStatusInterface
    {
        return $this->setData(self::SYNCED_COUNT, $syncedCount);
    }

    /**
     * @inheritdoc
     */
    public function getFailedCount(): int
    {
        return (int) $this->getData(self::FAILED_COUNT);
    }

    /**
     * @inheritdoc
     */
    public function setFailedCount(int $failedCount): SyncStatusInterface
    {
        return $this->setData(self::FAILED_COUNT, $failedCount);
    }

    /**
     * @inheritdoc
     */
    public function getPendingCount(): int
    {
        return (int) $this->getData(self::PENDING_COUNT);
    }

    /**
     * @inheritdoc
     */
    public function setPendingCount(int $pendingCount): SyncStatusInterface
    {
        return $this->setData(self::PENDING_COUNT, $pendingCount);
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): ?string
    {
        return $this->getData(self::STATUS);
    }

    /**
     * @inheritdoc
     */
    public function setStatus(string $status): SyncStatusInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritdoc
     */
    public function getLastSyncedAt(): ?string
    {
        return $this->getData(self::LAST_SYNCED_AT);
    }

    /**
     * @inheritdoc
     */
    public function setLastSyncedAt(?string $lastSyncedAt): SyncStatusInterface
    {
        return $this->setData(self::LAST_SYNCED_AT, $lastSyncedAt);
    }

    /**
     * @inheritdoc
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function setCreatedAt(string $createdAt): SyncStatusInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritdoc
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritdoc
     */
    public function setUpdatedAt(string $updatedAt): SyncStatusInterface
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }
}
