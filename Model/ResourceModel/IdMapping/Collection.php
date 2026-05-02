<?php
/**
 * MageClone MagentoMigrator ID Mapping Collection
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\ResourceModel\IdMapping;

use MageClone\MagentoMigrator\Model\IdMapping as IdMappingModel;
use MageClone\MagentoMigrator\Model\ResourceModel\IdMapping as IdMappingResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

/**
 * Class Collection
 *
 * Collection for ID mapping records.
 */
class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'mapping_id';

    /**
     * @var string
     */
    protected $_eventPrefix = 'mageclone_id_mapping_collection';

    /**
     * Initialize collection
     *
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(IdMappingModel::class, IdMappingResource::class);
    }
}
