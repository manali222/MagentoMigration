<?php
/**
 * MageClone MagentoMigrator Product Mapper
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Mapper;

/**
 * Maps source product data to destination format.
 */
class ProductMapper implements EntityMapperInterface
{
    /**
     * @inheritDoc
     */
    public function getEntityType(): string
    {
        return 'product';
    }

    /**
     * @inheritDoc
     */
    public function getNaturalKeyField(): ?string
    {
        return 'sku';
    }

    /**
     * @inheritDoc
     */
    public function mapToDestination(array $sourceData, array $idMappings = []): array
    {
        $categoryIds = [];
        if (isset($sourceData['category_ids']) && is_array($sourceData['category_ids'])) {
            $categoryMappings = $idMappings['category'] ?? [];
            foreach ($sourceData['category_ids'] as $sourceCategoryId) {
                if (isset($categoryMappings[$sourceCategoryId])) {
                    $categoryIds[] = $categoryMappings[$sourceCategoryId];
                }
            }
        }

        $mapped = [
            'sku' => $sourceData['sku'] ?? '',
            'name' => $sourceData['name'] ?? '',
            'type_id' => $sourceData['type_id'] ?? 'simple',
            'attribute_set_id' => $sourceData['attribute_set_id'] ?? 4,
            'status' => $sourceData['status'] ?? 1,
            'visibility' => $sourceData['visibility'] ?? 4,
            'price' => $sourceData['price'] ?? 0.0,
            'special_price' => $sourceData['special_price'] ?? null,
            'special_from_date' => $sourceData['special_from_date'] ?? null,
            'special_to_date' => $sourceData['special_to_date'] ?? null,
            'weight' => $sourceData['weight'] ?? null,
            'url_key' => $sourceData['url_key'] ?? null,
            'description' => $sourceData['description'] ?? null,
            'short_description' => $sourceData['short_description'] ?? null,
            'meta_title' => $sourceData['meta_title'] ?? null,
            'meta_description' => $sourceData['meta_description'] ?? null,
            'meta_keyword' => $sourceData['meta_keyword'] ?? null,
            'category_ids' => $categoryIds,
            'custom_attributes' => $sourceData['custom_attributes'] ?? [],
        ];

        if (isset($sourceData['media_gallery']) && is_array($sourceData['media_gallery'])) {
            $mapped['media_gallery'] = $this->mapMediaGallery($sourceData['media_gallery']);
        } else {
            $mapped['media_gallery'] = [];
        }

        if (isset($sourceData['stock_item']) && is_array($sourceData['stock_item'])) {
            $mapped['stock_item'] = $this->mapStockItem($sourceData['stock_item']);
        }

        if (isset($sourceData['tier_prices']) && is_array($sourceData['tier_prices'])) {
            $mapped['tier_prices'] = $this->mapTierPrices($sourceData['tier_prices']);
        } else {
            $mapped['tier_prices'] = [];
        }

        if (isset($sourceData['configurable_options']) && is_array($sourceData['configurable_options'])) {
            $mapped['configurable_options'] = $this->mapConfigurableOptions($sourceData['configurable_options']);
        }

        if (isset($sourceData['configurable_children_skus']) && is_array($sourceData['configurable_children_skus'])) {
            $mapped['configurable_children_skus'] = $sourceData['configurable_children_skus'];
        }

        return $mapped;
    }

    /**
     * Map media gallery entries, removing source value IDs
     *
     * @param array $entries
     * @return array
     */
    private function mapMediaGallery(array $entries): array
    {
        $mapped = [];

        foreach ($entries as $entry) {
            $mapped[] = [
                'file' => $entry['file'] ?? '',
                'media_type' => $entry['media_type'] ?? 'image',
                'label' => $entry['label'] ?? null,
                'position' => $entry['position'] ?? 0,
                'disabled' => $entry['disabled'] ?? false,
            ];
        }

        return $mapped;
    }

    /**
     * Map stock item data
     *
     * @param array $stockItem
     * @return array
     */
    private function mapStockItem(array $stockItem): array
    {
        return [
            'qty' => $stockItem['qty'] ?? 0.0,
            'is_in_stock' => $stockItem['is_in_stock'] ?? true,
            'manage_stock' => $stockItem['manage_stock'] ?? true,
            'min_qty' => $stockItem['min_qty'] ?? 0.0,
            'min_sale_qty' => $stockItem['min_sale_qty'] ?? 1.0,
            'max_sale_qty' => $stockItem['max_sale_qty'] ?? 10000.0,
        ];
    }

    /**
     * Map tier prices
     *
     * @param array $tierPrices
     * @return array
     */
    private function mapTierPrices(array $tierPrices): array
    {
        $mapped = [];

        foreach ($tierPrices as $tierPrice) {
            $mapped[] = [
                'customer_group_id' => $tierPrice['customer_group_id'] ?? 0,
                'qty' => $tierPrice['qty'] ?? 1.0,
                'value' => $tierPrice['value'] ?? 0.0,
                'percentage_value' => $tierPrice['percentage_value'] ?? null,
            ];
        }

        return $mapped;
    }

    /**
     * Map configurable product options
     *
     * @param array $options
     * @return array
     */
    private function mapConfigurableOptions(array $options): array
    {
        $mapped = [];

        foreach ($options as $option) {
            $values = [];
            if (isset($option['values']) && is_array($option['values'])) {
                foreach ($option['values'] as $value) {
                    $values[] = [
                        'value_index' => $value['value_index'] ?? 0,
                        'label' => $value['label'] ?? '',
                    ];
                }
            }

            $mapped[] = [
                'attribute_code' => $option['attribute_code'] ?? '',
                'label' => $option['label'] ?? '',
                'values' => $values,
            ];
        }

        return $mapped;
    }
}
