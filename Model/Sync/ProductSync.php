<?php
/**
 * MageClone MagentoMigrator Product Sync
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Sync;

use MageClone\MagentoMigrator\Api\GraphQlClientInterface;
use MageClone\MagentoMigrator\Api\IdMappingRepositoryInterface;
use MageClone\MagentoMigrator\Api\SyncLogRepositoryInterface;
use MageClone\MagentoMigrator\Api\Data\SyncLogInterfaceFactory;
use MageClone\MagentoMigrator\Api\Data\IdMappingInterfaceFactory;
use MageClone\MagentoMigrator\Model\Mapper\ProductMapper;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;

/**
 * Synchronizes product entities from source to destination.
 */
class ProductSync extends AbstractEntitySync
{
    /**
     * @var ProductRepositoryInterface
     */
    private ProductRepositoryInterface $productRepository;

    /**
     * @var ProductInterfaceFactory
     */
    private ProductInterfaceFactory $productFactory;

    /**
     * @var StockRegistryInterface
     */
    private StockRegistryInterface $stockRegistry;

    /**
     * @var ProductMapper
     */
    private ProductMapper $productMapper;

    /**
     * @param GraphQlClientInterface $graphQlClient
     * @param IdMappingRepositoryInterface $idMappingRepository
     * @param SyncLogRepositoryInterface $syncLogRepository
     * @param SyncLogInterfaceFactory $syncLogFactory
     * @param IdMappingInterfaceFactory $idMappingFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LoggerInterface $logger
     * @param ProductRepositoryInterface $productRepository
     * @param ProductInterfaceFactory $productFactory
     * @param StockRegistryInterface $stockRegistry
     * @param ProductMapper $productMapper
     */
    public function __construct(
        GraphQlClientInterface $graphQlClient,
        IdMappingRepositoryInterface $idMappingRepository,
        SyncLogRepositoryInterface $syncLogRepository,
        SyncLogInterfaceFactory $syncLogFactory,
        IdMappingInterfaceFactory $idMappingFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger,
        ProductRepositoryInterface $productRepository,
        ProductInterfaceFactory $productFactory,
        StockRegistryInterface $stockRegistry,
        ProductMapper $productMapper
    ) {
        parent::__construct(
            $graphQlClient,
            $idMappingRepository,
            $syncLogRepository,
            $syncLogFactory,
            $idMappingFactory,
            $searchCriteriaBuilder,
            $logger
        );

        $this->productRepository = $productRepository;
        $this->productFactory = $productFactory;
        $this->stockRegistry = $stockRegistry;
        $this->productMapper = $productMapper;
    }

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
    public function getDependencies(): array
    {
        return ['eav_attribute', 'category'];
    }

    /**
     * @inheritDoc
     */
    protected function getGraphQlQuery(): string
    {
        return <<<'GRAPHQL'
query($pageSize: Int!, $currentPage: Int!, $updatedSince: String) {
    magecloneProducts(pageSize: $pageSize, currentPage: $currentPage, updatedSince: $updatedSince) {
        items {
            entity_id
            sku
            name
            type_id
            attribute_set_id
            status
            visibility
            price
            special_price
            special_from_date
            special_to_date
            weight
            url_key
            description
            short_description
            meta_title
            meta_description
            meta_keyword
            created_at
            updated_at
            media_gallery {
                value_id
                file
                media_type
                label
                position
                disabled
            }
            stock_item {
                qty
                is_in_stock
                manage_stock
                min_qty
                min_sale_qty
                max_sale_qty
            }
            tier_prices {
                customer_group_id
                qty
                value
                percentage_value
            }
            category_ids
            custom_attributes {
                attribute_code
                value
            }
            configurable_options {
                attribute_id
                attribute_code
                label
                values {
                    value_index
                    label
                }
            }
            configurable_children_skus
        }
        total_count
        page_info {
            page_size
            current_page
            total_pages
        }
    }
}
GRAPHQL;
    }

    /**
     * @inheritDoc
     */
    protected function getResponseKey(): string
    {
        return 'magecloneProducts';
    }

    /**
     * @inheritDoc
     */
    protected function saveEntity(array $entityData): int
    {
        $idMappings = $this->resolveIdMappings(['eav_attribute', 'category']);
        $mapped = $this->productMapper->mapToDestination($entityData, $idMappings);

        $sku = $mapped['sku'] ?? '';

        // Check if product exists by SKU (natural key)
        $existingProduct = null;
        try {
            $existingProduct = $this->productRepository->get($sku);
        } catch (NoSuchEntityException $e) {
            // Product does not exist, will create new
        }

        if ($existingProduct !== null) {
            $product = $existingProduct;
        } else {
            $product = $this->productFactory->create();
        }

        $product->setSku($sku);
        $product->setName($mapped['name'] ?? '');
        $product->setTypeId($mapped['type_id'] ?? 'simple');
        $product->setAttributeSetId($mapped['attribute_set_id'] ?? 4);
        $product->setStatus($mapped['status'] ?? 1);
        $product->setVisibility($mapped['visibility'] ?? 4);
        $product->setPrice($mapped['price'] ?? 0.0);

        if ($mapped['special_price'] !== null) {
            $product->setCustomAttribute('special_price', $mapped['special_price']);
        }
        if ($mapped['special_from_date'] !== null) {
            $product->setCustomAttribute('special_from_date', $mapped['special_from_date']);
        }
        if ($mapped['special_to_date'] !== null) {
            $product->setCustomAttribute('special_to_date', $mapped['special_to_date']);
        }

        $product->setWeight($mapped['weight'] ?? null);

        if ($mapped['url_key'] !== null) {
            $product->setCustomAttribute('url_key', $mapped['url_key']);
        }
        if ($mapped['description'] !== null) {
            $product->setCustomAttribute('description', $mapped['description']);
        }
        if ($mapped['short_description'] !== null) {
            $product->setCustomAttribute('short_description', $mapped['short_description']);
        }
        if ($mapped['meta_title'] !== null) {
            $product->setCustomAttribute('meta_title', $mapped['meta_title']);
        }
        if ($mapped['meta_description'] !== null) {
            $product->setCustomAttribute('meta_description', $mapped['meta_description']);
        }
        if ($mapped['meta_keyword'] !== null) {
            $product->setCustomAttribute('meta_keyword', $mapped['meta_keyword']);
        }

        // Set custom attributes
        foreach ($mapped['custom_attributes'] as $attr) {
            $product->setCustomAttribute($attr['attribute_code'], $attr['value'] ?? null);
        }

        // Set category links
        if (!empty($mapped['category_ids'])) {
            $product->setCategoryIds($mapped['category_ids']);
        }

        // Set tier prices
        if (!empty($mapped['tier_prices'])) {
            $tierPrices = [];
            foreach ($mapped['tier_prices'] as $tp) {
                $tierPrices[] = [
                    'customer_group_id' => $tp['customer_group_id'] ?? 0,
                    'qty' => $tp['qty'] ?? 1.0,
                    'value' => $tp['value'] ?? 0.0,
                    'percentage_value' => $tp['percentage_value'] ?? null,
                ];
            }
            $product->setData('tier_price', $tierPrices);
        }

        $saved = $this->productRepository->save($product);
        $savedId = (int) $saved->getId();

        // Update stock information
        if (isset($mapped['stock_item'])) {
            $stockItem = $this->stockRegistry->getStockItemBySku($sku);
            $stockItem->setQty($mapped['stock_item']['qty'] ?? 0.0);
            $stockItem->setIsInStock($mapped['stock_item']['is_in_stock'] ?? true);
            $stockItem->setManageStock($mapped['stock_item']['manage_stock'] ?? true);
            $stockItem->setMinQty($mapped['stock_item']['min_qty'] ?? 0.0);
            $stockItem->setMinSaleQty($mapped['stock_item']['min_sale_qty'] ?? 1.0);
            $stockItem->setMaxSaleQty($mapped['stock_item']['max_sale_qty'] ?? 10000.0);
            $this->stockRegistry->updateStockItemBySku($sku, $stockItem);
        }

        return $savedId;
    }
}
