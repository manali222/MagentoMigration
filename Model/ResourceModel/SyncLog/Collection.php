<?php
/**
 * MageClone MagentoMigrator Sync Log Collection
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\ResourceModel\SyncLog;

use MageClone\MagentoMigrator\Model\SyncLog as SyncLogModel;
use MageClone\MagentoMigrator\Model\ResourceModel\SyncLog as SyncLogResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Class Collection
 *
 * Collection for sync log records.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'log_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'mageclone_sync_log_collection';

    /**
     * Initialize collection
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(SyncLogModel::class, SyncLogResource::class);
    }
}
