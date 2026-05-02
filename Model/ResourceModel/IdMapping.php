<?php
/**
 * MageClone MagentoMigrator ID Mapping Resource Model
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\ResourceModel;

use MageClone\MagentoMigrator\Api\Data\IdMappingInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class IdMapping
 *
 * Resource model for the mageclone_id_mapping table.
 */
class IdMapping extends AbstractDb
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(IdMappingInterface::TABLE_NAME, IdMappingInterface::MAPPING_ID);
    }
}
