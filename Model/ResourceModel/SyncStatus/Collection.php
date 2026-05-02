<?php
/**
 * MageClone MagentoMigrator Sync Status Collection
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\ResourceModel\SyncStatus;

use MageClone\MagentoMigrator\Model\SyncStatus as SyncStatusModel;
use MageClone\MagentoMigrator\Model\ResourceModel\SyncStatus as SyncStatusResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Class Collection
 *
 * Collection for sync status records.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'status_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'mageclone_sync_status_collection';

    /**
     * Initialize collection
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(SyncStatusModel::class, SyncStatusResource::class);
    }
}
