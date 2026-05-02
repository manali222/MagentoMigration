<?php
/**
 * MageClone MagentoMigrator CMS Page Sync
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
use MageClone\MagentoMigrator\Model\Mapper\CmsMapper;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Cms\Model\PageFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;

/**
 * Synchronizes CMS page entities from source to destination.
 */
class CmsPageSync extends AbstractEntitySync
{
    /**
     * @var PageRepositoryInterface
     */
    private PageRepositoryInterface $pageRepository;

    /**
     * @var PageFactory
     */
    private PageFactory $pageFactory;

    /**
     * @var CmsMapper
     */
    private CmsMapper $cmsMapper;

    /**
     * @param GraphQlClientInterface $graphQlClient
     * @param IdMappingRepositoryInterface $idMappingRepository
     * @param SyncLogRepositoryInterface $syncLogRepository
     * @param SyncLogInterfaceFactory $syncLogFactory
     * @param IdMappingInterfaceFactory $idMappingFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LoggerInterface $logger
     * @param PageRepositoryInterface $pageRepository
     * @param PageFactory $pageFactory
     * @param CmsMapper $cmsMapper
     */
    public function __construct(
        GraphQlClientInterface $graphQlClient,
        IdMappingRepositoryInterface $idMappingRepository,
        SyncLogRepositoryInterface $syncLogRepository,
        SyncLogInterfaceFactory $syncLogFactory,
        IdMappingInterfaceFactory $idMappingFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger,
        PageRepositoryInterface $pageRepository,
        PageFactory $pageFactory,
        CmsMapper $cmsMapper
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

        $this->pageRepository = $pageRepository;
        $this->pageFactory = $pageFactory;
        $this->cmsMapper = $cmsMapper;
    }

    /**
     * @inheritDoc
     */
    public function getEntityType(): string
    {
        return 'cms_page';
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
    magecloneCmsPages(pageSize: $pageSize, currentPage: $currentPage, updatedSince: $updatedSince) {
        items {
            page_id
            identifier
            title
            content
            content_heading
            page_layout
            meta_title
            meta_description
            meta_keywords
            is_active
            sort_order
            store_ids
            created_at
            updated_at
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
        return 'magecloneCmsPages';
    }

    /**
     * @inheritDoc
     */
    protected function saveEntity(array $entityData): int
    {
        $mapped = $this->cmsMapper->mapToDestination($entityData);
        $identifier = $mapped['identifier'] ?? '';

        // Check if page exists by identifier (natural key)
        $existingPage = null;
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('identifier', $identifier)
            ->create();

        $existingPages = $this->pageRepository->getList($searchCriteria);

        if ($existingPages->getTotalCount() > 0) {
            $items = $existingPages->getItems();
            $existingPage = reset($items);
        }

        if ($existingPage !== null) {
            $page = $existingPage;
        } else {
            $page = $this->pageFactory->create();
        }

        $page->setIdentifier($identifier);
        $page->setTitle($mapped['title'] ?? '');
        $page->setContent($mapped['content'] ?? '');
        $page->setIsActive($mapped['is_active'] ?? true);

        if (isset($mapped['content_heading'])) {
            $page->setContentHeading($mapped['content_heading']);
        }
        if (isset($mapped['page_layout'])) {
            $page->setPageLayout($mapped['page_layout']);
        }
        if (isset($mapped['meta_title'])) {
            $page->setMetaTitle($mapped['meta_title']);
        }
        if (isset($mapped['meta_description'])) {
            $page->setMetaDescription($mapped['meta_description']);
        }
        if (isset($mapped['meta_keywords'])) {
            $page->setMetaKeywords($mapped['meta_keywords']);
        }
        if (isset($mapped['sort_order'])) {
            $page->setSortOrder((string) $mapped['sort_order']);
        }
        if (isset($mapped['store_ids'])) {
            $page->setData('stores', $mapped['store_ids']);
        }

        $saved = $this->pageRepository->save($page);

        return (int) $saved->getId();
    }
}
