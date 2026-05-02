<?php
/**
 * MageClone MagentoMigrator Sync Service Interface
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Api;

/**
 * Interface SyncServiceInterface
 *
 * Primary service interface for orchestrating entity synchronization.
 */
interface SyncServiceInterface
{
    /**
     * Synchronize all enabled entity types
     *
     * @return void
     */
    public function syncAll(): void;

    /**
     * Synchronize a specific entity type
     *
     * @param string $entityType
     * @return void
     */
    public function syncEntity(string $entityType): void;

    /**
     * Get list of available (registered) entity types
     *
     * @return string[]
     */
    public function getAvailableEntityTypes(): array;

    /**
     * Get current sync status for all entity types
     *
     * @return \MageClone\MagentoMigrator\Api\Data\SyncStatusInterface[]
     */
    public function getSyncStatus(): array;
}
