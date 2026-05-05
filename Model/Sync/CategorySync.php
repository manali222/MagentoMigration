<?php
/**
 * MageClone MagentoMigrator Category Sync
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
use MageClone\MagentoMigrator\Model\Mapper\CategoryMapper;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterfaceFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;

/**
 * Synchronizes category entities from source to destination.
 */
class CategorySync extends AbstractEntitySync
{
    /**
     * @var CategoryRepositoryInterface
     */
    private CategoryRepositoryInterface $categoryRepository;

    /**
     * @var CategoryInterfaceFactory
     */
    private CategoryInterfaceFactory $categoryFactory;

    /**
     * @var CategoryCollectionFactory
     */
    private CategoryCollectionFactory $categoryCollectionFactory;

    /**
     * @var CategoryMapper
     */
    private CategoryMapper $categoryMapper;

    /**
     * @param GraphQlClientInterface $graphQlClient
     * @param IdMappingRepositoryInterface $idMappingRepository
     * @param SyncLogRepositoryInterface $syncLogRepository
     * @param SyncLogInterfaceFactory $syncLogFactory
     * @param IdMappingInterfaceFactory $idMappingFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LoggerInterface $logger
     * @param CategoryRepositoryInterface $categoryRepository
     * @param CategoryInterfaceFactory $categoryFactory
     * @param CategoryCollectionFactory $categoryCollectionFactory
     * @param CategoryMapper $categoryMapper
     */
    public function __construct(
        GraphQlClientInterface $graphQlClient,
        IdMappingRepositoryInterface $idMappingRepository,
        SyncLogRepositoryInterface $syncLogRepository,
        SyncLogInterfaceFactory $syncLogFactory,
        IdMappingInterfaceFactory $idMappingFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger,
        CategoryRepositoryInterface $categoryRepository,
        CategoryInterfaceFactory $categoryFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        CategoryMapper $categoryMapper
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

        $this->categoryRepository = $categoryRepository;
        $this->categoryFactory = $categoryFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->categoryMapper = $categoryMapper;
    }

    /**
     * @inheritDoc
     */
    public function getEntityType(): string
    {
        return 'category';
    }

    /**
     * @inheritDoc
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    protected function getGraphQlQuery(): string
    {
        return <<<'GRAPHQL'
query($pageSize: Int!, $currentPage: Int!, $updatedSince: String) {
    magecloneCategories(pageSize: $pageSize, currentPage: $currentPage, updatedSince: $updatedSince) {
        items {
            entity_id
            name
            parent_id
            path
            level
            position
            is_active
            include_in_menu
            url_key
            description
            meta_title
            meta_description
            created_at
            updated_at
            custom_attributes {
                attribute_code
                value
            }
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
        return 'magecloneCategories';
    }

    /**
     * @inheritDoc
     */
    protected function saveEntity(array $entityData): int
    {
        $idMappings = $this->resolveIdMappings(['category']);
        $mapped = $this->categoryMapper->mapToDestination($entityData, $idMappings);

        $urlKey = $mapped['url_key'] ?? null;
        $name = $mapped['name'] ?? '';
        $parentId = $mapped['parent_id'];

        // Try to find existing category by URL key (any parent) or by name under same parent
        $existingCategory = null;
        if ($urlKey !== null) {
            $collection = $this->categoryCollectionFactory->create();
            $collection->addAttributeToFilter('url_key', $urlKey);
            $collection->setPageSize(1);

            if ($collection->getSize() > 0) {
                $existingCategory = $collection->getFirstItem();
            }
        }

        // Also try matching by name under same parent if no URL key match
        if ($existingCategory === null && $name !== '' && $parentId !== null) {
            $collection = $this->categoryCollectionFactory->create();
            $collection->addAttributeToFilter('name', $name);
            $collection->addFieldToFilter('parent_id', $parentId);
            $collection->setPageSize(1);

            if ($collection->getSize() > 0) {
                $existingCategory = $collection->getFirstItem();
            }
        }

        if ($existingCategory !== null && $existingCategory->getId()) {
            $category = $this->categoryRepository->get((int) $existingCategory->getId());
        } else {
            $category = $this->categoryFactory->create();
        }

        $category->setName($name);
        $category->setIsActive($mapped['is_active'] ?? true);
        $category->setPosition($mapped['position'] ?? 0);
        $category->setCustomAttribute('url_key', $urlKey);
        $category->setCustomAttribute('description', $mapped['description'] ?? null);
        $category->setCustomAttribute('meta_title', $mapped['meta_title'] ?? null);
        $category->setCustomAttribute('meta_description', $mapped['meta_description'] ?? null);

        if (isset($mapped['include_in_menu'])) {
            $category->setCustomAttribute('include_in_menu', $mapped['include_in_menu']);
        }

        if ($parentId !== null) {
            $category->setParentId($parentId);
        }

        // Set custom attributes
        if (!empty($mapped['custom_attributes'])) {
            foreach ($mapped['custom_attributes'] as $attr) {
                $category->setCustomAttribute($attr['attribute_code'], $attr['value'] ?? null);
            }
        }

        $saved = $this->categoryRepository->save($category);

        return (int) $saved->getId();
    }
}
