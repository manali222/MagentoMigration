<?php

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Resolver;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Resolver for magecloneProducts query.
 *
 * Returns paginated product data with media gallery, stock, tier prices,
 * category IDs, custom attributes, and configurable product data.
 */
class Products implements ResolverInterface
{
    /**
     * @var ProductCollectionFactory
     */
    private ProductCollectionFactory $productCollectionFactory;

    /**
     * @var StockRegistryInterface
     */
    private StockRegistryInterface $stockRegistry;

    /**
     * @var Configurable
     */
    private Configurable $configurableType;

    /**
     * @param ProductCollectionFactory $productCollectionFactory
     * @param StockRegistryInterface $stockRegistry
     * @param Configurable $configurableType
     */
    public function __construct(
        ProductCollectionFactory $productCollectionFactory,
        StockRegistryInterface $stockRegistry,
        Configurable $configurableType
    ) {
        $this->productCollectionFactory = $productCollectionFactory;
        $this->stockRegistry = $stockRegistry;
        $this->configurableType = $configurableType;
    }

    /**
     * Resolve products with full detail.
     *
     * @param Field $field
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @param ResolveInfo $info
     * @param array|null $value
     * @param array|null $args
     * @return array
     * @throws GraphQlAuthorizationException
     * @throws GraphQlInputException
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        array $value = null,
        array $args = null
    ): array {
        $this->authorize($context);

        $pageSize = (int) ($args['pageSize'] ?? 50);
        $currentPage = (int) ($args['currentPage'] ?? 1);
        $updatedSince = $args['updatedSince'] ?? null;

        if ($pageSize < 1 || $pageSize > 300) {
            throw new GraphQlInputException(__('pageSize must be between 1 and 300.'));
        }
        if ($currentPage < 1) {
            throw new GraphQlInputException(__('currentPage must be 1 or greater.'));
        }

        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToSelect('*');
        $collection->addMediaGalleryData();

        if ($updatedSince !== null) {
            $collection->addFieldToFilter('updated_at', ['gteq' => $updatedSince]);
        }

        $totalCount = $collection->getSize();
        $collection->setPageSize($pageSize);
        $collection->setCurPage($currentPage);

        $totalPages = $pageSize > 0 ? (int) ceil($totalCount / $pageSize) : 0;

        $items = [];
        foreach ($collection as $product) {
            $items[] = $this->mapProduct($product);
        }

        return [
            'items' => $items,
            'total_count' => $totalCount,
            'page_info' => [
                'page_size' => $pageSize,
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
            ],
        ];
    }

    /**
     * Map a product model to the GraphQL output array.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    private function mapProduct($product): array
    {
        $data = [
            'entity_id' => (int) $product->getId(),
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'type_id' => $product->getTypeId(),
            'attribute_set_id' => (int) $product->getAttributeSetId(),
            'status' => (int) $product->getStatus(),
            'visibility' => (int) $product->getVisibility(),
            'price' => $product->getPrice() !== null ? (float) $product->getPrice() : null,
            'special_price' => $product->getSpecialPrice() !== null
                ? (float) $product->getSpecialPrice() : null,
            'special_from_date' => $product->getSpecialFromDate(),
            'special_to_date' => $product->getSpecialToDate(),
            'weight' => $product->getWeight() !== null ? (float) $product->getWeight() : null,
            'url_key' => $product->getUrlKey(),
            'description' => $product->getDescription(),
            'short_description' => $product->getShortDescription(),
            'meta_title' => $product->getMetaTitle(),
            'meta_description' => $product->getMetaDescription(),
            'meta_keyword' => $product->getMetaKeyword(),
            'created_at' => $product->getCreatedAt(),
            'updated_at' => $product->getUpdatedAt(),
            'media_gallery' => $this->getMediaGallery($product),
            'stock_item' => $this->getStockItem($product),
            'tier_prices' => $this->getTierPrices($product),
            'category_ids' => array_map('intval', $product->getCategoryIds()),
            'custom_attributes' => $this->getCustomAttributes($product),
            'configurable_options' => [],
            'configurable_children_skus' => [],
        ];

        if ($product->getTypeId() === Configurable::TYPE_CODE) {
            $data['configurable_options'] = $this->getConfigurableOptions($product);
            $data['configurable_children_skus'] = $this->getConfigurableChildrenSkus($product);
        }

        return $data;
    }

    /**
     * Get media gallery entries for a product.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    private function getMediaGallery($product): array
    {
        $entries = [];
        $gallery = $product->getMediaGalleryEntries();
        if ($gallery === null) {
            return $entries;
        }

        foreach ($gallery as $entry) {
            $entries[] = [
                'value_id' => (int) $entry->getId(),
                'file' => $entry->getFile(),
                'media_type' => $entry->getMediaType(),
                'label' => $entry->getLabel(),
                'position' => (int) $entry->getPosition(),
                'disabled' => (bool) $entry->isDisabled(),
            ];
        }

        return $entries;
    }

    /**
     * Get stock item data for a product.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array|null
     */
    private function getStockItem($product): ?array
    {
        try {
            $stockItem = $this->stockRegistry->getStockItem($product->getId());
        } catch (\Exception $e) {
            return null;
        }

        return [
            'qty' => (float) $stockItem->getQty(),
            'is_in_stock' => (bool) $stockItem->getIsInStock(),
            'manage_stock' => (bool) $stockItem->getManageStock(),
            'min_qty' => (float) $stockItem->getMinQty(),
            'min_sale_qty' => (float) $stockItem->getMinSaleQty(),
            'max_sale_qty' => (float) $stockItem->getMaxSaleQty(),
        ];
    }

    /**
     * Get tier prices for a product.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    private function getTierPrices($product): array
    {
        $tierPrices = $product->getTierPrices();
        if ($tierPrices === null) {
            return [];
        }

        $result = [];
        foreach ($tierPrices as $tierPrice) {
            $result[] = [
                'customer_group_id' => (int) $tierPrice->getCustomerGroupId(),
                'qty' => (float) $tierPrice->getQty(),
                'value' => (float) $tierPrice->getValue(),
                'percentage_value' => $tierPrice->getExtensionAttributes()
                    ? $tierPrice->getExtensionAttributes()->getPercentageValue()
                    : null,
            ];
        }

        return $result;
    }

    /**
     * Get custom attributes for a product, excluding standard fields.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    private function getCustomAttributes($product): array
    {
        $customAttributes = [];
        $skipAttributes = [
            'entity_id', 'sku', 'name', 'type_id', 'attribute_set_id',
            'status', 'visibility', 'price', 'special_price',
            'special_from_date', 'special_to_date', 'weight', 'url_key',
            'description', 'short_description', 'meta_title',
            'meta_description', 'meta_keyword', 'created_at', 'updated_at',
            'media_gallery', 'tier_price', 'category_ids',
            'quantity_and_stock_status', 'options',
        ];

        $attributes = $product->getCustomAttributes();
        if ($attributes === null) {
            return $customAttributes;
        }

        foreach ($attributes as $attribute) {
            if (in_array($attribute->getAttributeCode(), $skipAttributes, true)) {
                continue;
            }
            $value = $attribute->getValue();
            $customAttributes[] = [
                'attribute_code' => $attribute->getAttributeCode(),
                'value' => is_string($value) ? $value : (is_array($value) ? json_encode($value) : (string) $value),
            ];
        }

        return $customAttributes;
    }

    /**
     * Get configurable product options.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    private function getConfigurableOptions($product): array
    {
        $options = [];
        $configurableAttributes = $this->configurableType->getConfigurableAttributes($product);

        foreach ($configurableAttributes as $attribute) {
            $productAttribute = $attribute->getProductAttribute();
            $optionValues = [];

            if ($productAttribute !== null) {
                $attributeOptions = $productAttribute->getSource()->getAllOptions(false);
                foreach ($attributeOptions as $option) {
                    $optionValues[] = [
                        'value_index' => (int) $option['value'],
                        'label' => (string) $option['label'],
                    ];
                }
            }

            $options[] = [
                'attribute_id' => (int) $attribute->getAttributeId(),
                'attribute_code' => $productAttribute ? $productAttribute->getAttributeCode() : '',
                'label' => $attribute->getLabel(),
                'values' => $optionValues,
            ];
        }

        return $options;
    }

    /**
     * Get SKUs of configurable product children.
     *
     * @param \Magento\Catalog\Model\Product $product
     * @return array
     */
    private function getConfigurableChildrenSkus($product): array
    {
        $children = $this->configurableType->getUsedProducts($product);
        $skus = [];
        foreach ($children as $child) {
            $skus[] = $child->getSku();
        }

        return $skus;
    }

    /**
     * Verify the request is from an admin context, not a customer.
     *
     * @param \Magento\Framework\GraphQl\Query\Resolver\ContextInterface $context
     * @return void
     * @throws GraphQlAuthorizationException
     */
    private function authorize($context): void
    {
        if ($context->getExtensionAttributes()->getIsCustomer() === true) {
            throw new GraphQlAuthorizationException(
                __('The current customer is not authorized to access this resource.')
            );
        }
    }
}
