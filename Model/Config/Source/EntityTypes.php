<?php
/**
 * MageClone MagentoMigrator Entity Types Source Model
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class EntityTypes
 *
 * Provides available entity types for system configuration multiselect.
 */
class EntityTypes implements OptionSourceInterface
{
    /**
     * Get available entity types as option array
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'customer', 'label' => __('Customers')],
            ['value' => 'order', 'label' => __('Orders')],
            ['value' => 'product', 'label' => __('Products')],
            ['value' => 'category', 'label' => __('Categories')],
            ['value' => 'cms_page', 'label' => __('CMS Pages')],
            ['value' => 'cms_block', 'label' => __('CMS Blocks')],
            ['value' => 'store_config', 'label' => __('Store Configuration')],
            ['value' => 'eav_attribute', 'label' => __('EAV Attributes')],
            ['value' => 'custom_table', 'label' => __('Custom Tables')],
        ];
    }
}
