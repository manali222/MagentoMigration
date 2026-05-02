<?php
/**
 * MageClone MagentoMigrator Entity Mapper Interface
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Mapper;

/**
 * Interface EntityMapperInterface
 *
 * Maps source entity data to a format suitable for the destination Magento instance.
 */
interface EntityMapperInterface
{
    /**
     * Map source entity data to destination format
     *
     * @param array $sourceData The raw entity data from the source instance
     * @param array $idMappings Previously resolved ID mappings keyed by entity type
     * @return array The mapped data ready for saving in the destination
     */
    public function mapToDestination(array $sourceData, array $idMappings = []): array;

    /**
     * Get the entity type this mapper handles
     *
     * @return string
     */
    public function getEntityType(): string;

    /**
     * Get the field used as a natural key for matching existing entities
     *
     * @return string|null Null if no natural key exists (e.g., path-based matching)
     */
    public function getNaturalKeyField(): ?string;
}
