<?php
/**
 * MageClone MagentoMigrator Sync Log Model
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model;

use MageClone\MagentoMigrator\Api\Data\SyncLogInterface;
use MageClone\MagentoMigrator\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Class SyncLog
 *
 * Data model for sync log records.
 */
class SyncLog extends AbstractModel implements SyncLogInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'mageclone_sync_log';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(SyncLogResource::class);
    }

    /**
     * @inheritdoc
     */
    public function getLogId(): ?int
    {
        $value = $this->getData(self::LOG_ID);
        return $value !== null ? (int) $value : null;
    }

    /**
     * @inheritdoc
     */
    public function setLogId(int $logId): SyncLogInterface
    {
        return $this->setData(self::LOG_ID, $logId);
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
    public function setEntityType(string $entityType): SyncLogInterface
    {
        return $this->setData(self::ENTITY_TYPE, $entityType);
    }

    /**
     * @inheritdoc
     */
    public function getSourceId(): ?int
    {
        $value = $this->getData(self::SOURCE_ID);
        return $value !== null ? (int) $value : null;
    }

    /**
     * @inheritdoc
     */
    public function setSourceId(?int $sourceId): SyncLogInterface
    {
        return $this->setData(self::SOURCE_ID, $sourceId);
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
    public function setStatus(string $status): SyncLogInterface
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritdoc
     */
    public function getMessage(): ?string
    {
        return $this->getData(self::MESSAGE);
    }

    /**
     * @inheritdoc
     */
    public function setMessage(?string $message): SyncLogInterface
    {
        return $this->setData(self::MESSAGE, $message);
    }

    /**
     * @inheritdoc
     */
    public function getBatchId(): ?string
    {
        return $this->getData(self::BATCH_ID);
    }

    /**
     * @inheritdoc
     */
    public function setBatchId(?string $batchId): SyncLogInterface
    {
        return $this->setData(self::BATCH_ID, $batchId);
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
    public function setCreatedAt(string $createdAt): SyncLogInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }
}
