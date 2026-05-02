<?php

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Resolver;

use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Resolver for magecloneCmsPages query.
 *
 * Returns paginated CMS page data with store associations.
 */
class CmsPages implements ResolverInterface
{
    /**
     * @var PageCollectionFactory
     */
    private PageCollectionFactory $pageCollectionFactory;

    /**
     * @param PageCollectionFactory $pageCollectionFactory
     */
    public function __construct(
        PageCollectionFactory $pageCollectionFactory
    ) {
        $this->pageCollectionFactory = $pageCollectionFactory;
    }

    /**
     * Resolve CMS pages.
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

        $collection = $this->pageCollectionFactory->create();

        if ($updatedSince !== null) {
            $collection->addFieldToFilter('update_time', ['gteq' => $updatedSince]);
        }

        $totalCount = $collection->getSize();
        $collection->setPageSize($pageSize);
        $collection->setCurPage($currentPage);

        $totalPages = $pageSize > 0 ? (int) ceil($totalCount / $pageSize) : 0;

        $items = [];
        foreach ($collection as $page) {
            $storeIds = $page->getStoreId();
            if (!is_array($storeIds)) {
                $storeIds = $page->getData('store_id');
                if (!is_array($storeIds)) {
                    $storeIds = [(int) $storeIds];
                }
            }
            $storeIds = array_map('intval', $storeIds);

            $items[] = [
                'page_id' => (int) $page->getId(),
                'identifier' => $page->getIdentifier(),
                'title' => $page->getTitle(),
                'content' => $page->getContent(),
                'content_heading' => $page->getContentHeading(),
                'page_layout' => $page->getPageLayout(),
                'meta_title' => $page->getMetaTitle(),
                'meta_description' => $page->getMetaDescription(),
                'meta_keywords' => $page->getMetaKeywords(),
                'is_active' => (bool) $page->getIsActive(),
                'sort_order' => (int) $page->getSortOrder(),
                'store_ids' => $storeIds,
                'created_at' => $page->getCreationTime(),
                'updated_at' => $page->getUpdateTime(),
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
