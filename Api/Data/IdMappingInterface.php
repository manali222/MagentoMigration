<?php
/**
 * MageClone MagentoMigrator ID Mapping Data Interface
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Api\Data;

/**
 * Interface IdMappingInterface
 *
 * Represents a mapping between source and destination entity IDs.
 */
interface IdMappingInterface
{
    /**
     * Table name constant
     */
    public const TABLE_NAME = 'mageclone_id_mapping';

    /**
     * Column name constants
     */
    public const MAPPING_ID = 'mapping_id';
    public const ENTITY_TYPE = 'entity_type';
    public const SOURCE_ID = 'source_id';
    public const DESTINATION_ID = 'destination_id';
    public const CHECKSUM = 'checksum';
    public const CREATED_AT = 'created_at';

    /**
     * Get mapping ID
     *
     * @return int|null
     */
    public function getMappingId(): ?int;

    /**
     * Set mapping ID
     *
     * @param int $mappingId
     * @return $this
     */
    public function setMappingId(int $mappingId): self;

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
     * @param int $sourceId
     * @return $this
     */
    public function setSourceId(int $sourceId): self;

    /**
     * Get destination ID
     *
     * @return int|null
     */
    public function getDestinationId(): ?int;

    /**
     * Set destination ID
     *
     * @param int $destinationId
     * @return $this
     */
    public function setDestinationId(int $destinationId): self;

    /**
     * Get checksum
     *
     * @return string|null
     */
    public function getChecksum(): ?string;

    /**
     * Set checksum
     *
     * @param string|null $checksum
     * @return $this
     */
    public function setChecksum(?string $checksum): self;

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
