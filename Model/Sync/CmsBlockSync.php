<?php
/**
 * MageClone MagentoMigrator CMS Block Sync
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
use Magento\Cms\Api\BlockRepositoryInterface;
use Magento\Cms\Model\BlockFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;

/**
 * Synchronizes CMS block entities from source to destination.
 */
class CmsBlockSync extends AbstractEntitySync
{
    /**
     * @var BlockRepositoryInterface
     */
    private BlockRepositoryInterface $blockRepository;

    /**
     * @var BlockFactory
     */
    private BlockFactory $blockFactory;

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
     * @param BlockRepositoryInterface $blockRepository
     * @param BlockFactory $blockFactory
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
        BlockRepositoryInterface $blockRepository,
        BlockFactory $blockFactory,
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

        $this->blockRepository = $blockRepository;
        $this->blockFactory = $blockFactory;
        $this->cmsMapper = $cmsMapper;
    }

    /**
     * @inheritDoc
     */
    public function getEntityType(): string
    {
        return 'cms_block';
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
    magecloneCmsBlocks(pageSize: $pageSize, currentPage: $currentPage, updatedSince: $updatedSince) {
        items {
            block_id
            identifier
            title
            content
            is_active
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
        return 'magecloneCmsBlocks';
    }

    /**
     * @inheritDoc
     */
    protected function saveEntity(array $entityData): int
    {
        $mapped = $this->cmsMapper->mapToDestination($entityData);
        $identifier = $mapped['identifier'] ?? '';

        // Check if block exists by identifier (natural key)
        $existingBlock = null;
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('identifier', $identifier)
            ->create();

        $existingBlocks = $this->blockRepository->getList($searchCriteria);

        if ($existingBlocks->getTotalCount() > 0) {
            $items = $existingBlocks->getItems();
            $existingBlock = reset($items);
        }

        if ($existingBlock !== null) {
            $block = $existingBlock;
        } else {
            $block = $this->blockFactory->create();
        }

        $block->setIdentifier($identifier);
        $block->setTitle($mapped['title'] ?? '');
        $block->setContent($mapped['content'] ?? '');
        $block->setIsActive($mapped['is_active'] ?? true);

        if (isset($mapped['store_ids'])) {
            $block->setData('stores', $mapped['store_ids']);
        }

        $saved = $this->blockRepository->save($block);

        return (int) $saved->getId();
    }
}
