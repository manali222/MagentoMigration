<?php
/**
 * MageClone MagentoMigrator Entity Sync Interface
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Sync;

/**
 * Interface EntitySyncInterface
 *
 * Defines the contract for synchronizing a specific entity type from source to destination.
 */
interface EntitySyncInterface
{
    /**
     * Get the entity type this sync handler processes
     *
     * @return string
     */
    public function getEntityType(): string;

    /**
     * Get entity types that must be synced before this one
     *
     * @return string[]
     */
    public function getDependencies(): array;

    /**
     * Fetch a page of entities from the source instance via GraphQL
     *
     * @param int $page The page number (1-based)
     * @param int $pageSize Number of items per page
     * @param string|null $updatedSince Only fetch entities updated after this datetime
     * @return array The list of entity data arrays
     */
    public function fetchPage(int $page, int $pageSize, ?string $updatedSince = null): array;

    /**
     * Save a batch of entities to the destination instance
     *
     * @param array $items Array of entity data to save
     * @param array $idMappings Previously resolved ID mappings keyed by entity type
     * @return array Result with keys: 'synced' (int), 'failed' (int), 'errors' (array)
     */
    public function saveBatch(array $items, array $idMappings = []): array;

    /**
     * Get the total count of entities available on the source instance
     *
     * @param string|null $updatedSince Only count entities updated after this datetime
     * @return int
     */
    public function getSourceCount(?string $updatedSince = null): int;
}
