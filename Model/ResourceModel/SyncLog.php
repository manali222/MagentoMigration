<?php
/**
 * MageClone MagentoMigrator Sync Log Resource Model
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\ResourceModel;

use MageClone\MagentoMigrator\Api\Data\SyncLogInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class SyncLog
 *
 * Resource model for the mageclone_sync_log table.
 */
class SyncLog extends AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(SyncLogInterface::TABLE_NAME, SyncLogInterface::LOG_ID);
    }
}
