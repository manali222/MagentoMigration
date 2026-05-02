<?php

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Resolver;

use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Resolver for magecloneCategories query.
 *
 * Returns paginated category data with hierarchy information.
 */
class Categories implements ResolverInterface
{
    /**
     * @var CategoryCollectionFactory
     */
    private CategoryCollectionFactory $categoryCollectionFactory;

    /**
     * @param CategoryCollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        CategoryCollectionFactory $categoryCollectionFactory
    ) {
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * Resolve categories with hierarchy.
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

        $collection = $this->categoryCollectionFactory->create();
        $collection->addAttributeToSelect('*');

        if ($updatedSince !== null) {
            $collection->addFieldToFilter('updated_at', ['gteq' => $updatedSince]);
        }

        $totalCount = $collection->getSize();
        $collection->setPageSize($pageSize);
        $collection->setCurPage($currentPage);

        $totalPages = $pageSize > 0 ? (int) ceil($totalCount / $pageSize) : 0;

        $items = [];
        foreach ($collection as $category) {
            $items[] = [
                'entity_id' => (int) $category->getId(),
                'name' => $category->getName(),
                'parent_id' => (int) $category->getParentId(),
                'path' => $category->getPath(),
                'level' => (int) $category->getLevel(),
                'position' => (int) $category->getPosition(),
                'is_active' => (bool) $category->getIsActive(),
                'include_in_menu' => (bool) $category->getIncludeInMenu(),
                'url_key' => $category->getUrlKey(),
                'description' => $category->getDescription(),
                'meta_title' => $category->getMetaTitle(),
                'meta_description' => $category->getData('meta_description'),
                'created_at' => $category->getCreatedAt(),
                'updated_at' => $category->getUpdatedAt(),
                'custom_attributes' => $this->getCustomAttributes($category),
            ];
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
     * Extract custom attributes from category entity.
     *
     * @param \Magento\Catalog\Model\Category $category
     * @return array
     */
    private function getCustomAttributes($category): array
    {
        $customAttributes = [];
        $skipAttributes = [
            'entity_id', 'name', 'parent_id', 'path', 'level', 'position',
            'is_active', 'include_in_menu', 'url_key', 'description',
            'meta_title', 'meta_description', 'created_at', 'updated_at',
            'children_count', 'all_children', 'path_in_store',
            'available_sort_by', 'default_sort_by',
        ];

        $attributes = $category->getCustomAttributes();
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
