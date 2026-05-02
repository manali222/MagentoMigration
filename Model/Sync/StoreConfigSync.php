<?php
/**
 * MageClone MagentoMigrator Store Config Sync
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
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;

/**
 * Synchronizes store configuration values from source to destination.
 */
class StoreConfigSync extends AbstractEntitySync
{
    /**
     * @var WriterInterface
     */
    private WriterInterface $configWriter;

    /**
     * @param GraphQlClientInterface $graphQlClient
     * @param IdMappingRepositoryInterface $idMappingRepository
     * @param SyncLogRepositoryInterface $syncLogRepository
     * @param SyncLogInterfaceFactory $syncLogFactory
     * @param IdMappingInterfaceFactory $idMappingFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LoggerInterface $logger
     * @param WriterInterface $configWriter
     */
    public function __construct(
        GraphQlClientInterface $graphQlClient,
        IdMappingRepositoryInterface $idMappingRepository,
        SyncLogRepositoryInterface $syncLogRepository,
        SyncLogInterfaceFactory $syncLogFactory,
        IdMappingInterfaceFactory $idMappingFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger,
        WriterInterface $configWriter
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

        $this->configWriter = $configWriter;
    }

    /**
     * @inheritDoc
     */
    public function getEntityType(): string
    {
        return 'store_config';
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
     *
     * Store configs use a different fetch pattern since they require path parameters.
     */
    public function fetchPage(int $page, int $pageSize, ?string $updatedSince = null): array
    {
        $query = $this->getGraphQlQuery();
        $data = $this->graphQlClient->query($query, [
            'paths' => $this->getDefaultConfigPaths(),
        ]);

        $responseKey = $this->getResponseKey();

        if (!isset($data[$responseKey]) || !is_array($data[$responseKey])) {
            return [];
        }

        return $data[$responseKey];
    }

    /**
     * @inheritDoc
     */
    public function getSourceCount(?string $updatedSince = null): int
    {
        $items = $this->fetchPage(1, 1, $updatedSince);

        return count($items);
    }

    /**
     * @inheritDoc
     */
    protected function getGraphQlQuery(): string
    {
        return <<<'GRAPHQL'
query($paths: [String!]!) {
    magecloneStoreConfigs(paths: $paths) {
        path
        value
    }
}
GRAPHQL;
    }

    /**
     * @inheritDoc
     */
    protected function getResponseKey(): string
    {
        return 'magecloneStoreConfigs';
    }

    /**
     * @inheritDoc
     */
    protected function saveEntity(array $entityData): int
    {
        $path = $entityData['path'] ?? '';
        $value = $entityData['value'] ?? null;

        if ($path === '') {
            throw new \InvalidArgumentException('Store config path cannot be empty.');
        }

        $this->configWriter->save($path, $value);

        // Configs do not have numeric IDs
        return 0;
    }

    /**
     * Get the default list of configuration paths to sync
     *
     * @return string[]
     */
    private function getDefaultConfigPaths(): array
    {
        return [
            'general/store_information/name',
            'general/store_information/phone',
            'general/store_information/hours',
            'general/store_information/country_id',
            'general/store_information/region_id',
            'general/store_information/postcode',
            'general/store_information/city',
            'general/store_information/street_line1',
            'general/store_information/street_line2',
            'general/locale/code',
            'general/locale/timezone',
            'general/locale/weight_unit',
            'currency/options/base',
            'currency/options/default',
            'currency/options/allow',
            'design/theme/theme_id',
            'design/head/default_title',
            'design/head/default_description',
            'design/header/logo_src',
            'design/footer/copyright',
            'catalog/seo/product_url_suffix',
            'catalog/seo/category_url_suffix',
            'tax/defaults/country',
            'tax/defaults/region',
            'tax/defaults/postcode',
            'shipping/origin/country_id',
            'shipping/origin/region_id',
            'shipping/origin/postcode',
            'shipping/origin/city',
            'shipping/origin/street_line1',
        ];
    }
}
