<?php
/**
 * MageClone MagentoMigrator Sync Status Resource Model
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\ResourceModel;

use MageClone\MagentoMigrator\Api\Data\SyncStatusInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class SyncStatus
 *
 * Resource model for the mageclone_sync_status table.
 */
class SyncStatus extends AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(SyncStatusInterface::TABLE_NAME, SyncStatusInterface::STATUS_ID);
    }
}
