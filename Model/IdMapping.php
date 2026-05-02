<?php
/**
 * MageClone MagentoMigrator ID Mapping Model
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model;

use MageClone\MagentoMigrator\Api\Data\IdMappingInterface;
use MageClone\MagentoMigrator\Model\ResourceModel\IdMapping as IdMappingResource;
use Magento\Framework\Model\AbstractModel;

/**
 * Class IdMapping
 *
 * Data model for entity ID mapping records.
 */
class IdMapping extends AbstractModel implements IdMappingInterface
{
    /**
     * @var string
     */
    protected $_eventPrefix = 'mageclone_id_mapping';

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(IdMappingResource::class);
    }

    /**
     * @inheritdoc
     */
    public function getMappingId(): ?int
    {
        $value = $this->getData(self::MAPPING_ID);
        return $value !== null ? (int) $value : null;
    }

    /**
     * @inheritdoc
     */
    public function setMappingId(int $mappingId): IdMappingInterface
    {
        return $this->setData(self::MAPPING_ID, $mappingId);
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
    public function setEntityType(string $entityType): IdMappingInterface
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
    public function setSourceId(int $sourceId): IdMappingInterface
    {
        return $this->setData(self::SOURCE_ID, $sourceId);
    }

    /**
     * @inheritdoc
     */
    public function getDestinationId(): ?int
    {
        $value = $this->getData(self::DESTINATION_ID);
        return $value !== null ? (int) $value : null;
    }

    /**
     * @inheritdoc
     */
    public function setDestinationId(int $destinationId): IdMappingInterface
    {
        return $this->setData(self::DESTINATION_ID, $destinationId);
    }

    /**
     * @inheritdoc
     */
    public function getChecksum(): ?string
    {
        return $this->getData(self::CHECKSUM);
    }

    /**
     * @inheritdoc
     */
    public function setChecksum(?string $checksum): IdMappingInterface
    {
        return $this->setData(self::CHECKSUM, $checksum);
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
    public function setCreatedAt(string $createdAt): IdMappingInterface
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }
}
