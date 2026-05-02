<?php
/**
 * MageClone MagentoMigrator Custom Table Sync
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
use MageClone\MagentoMigrator\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;

/**
 * Synchronizes custom database table data from source to destination.
 */
class CustomTableSync extends AbstractEntitySync
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var string|null
     */
    private ?string $currentTableName = null;

    /**
     * @param GraphQlClientInterface $graphQlClient
     * @param IdMappingRepositoryInterface $idMappingRepository
     * @param SyncLogRepositoryInterface $syncLogRepository
     * @param SyncLogInterfaceFactory $syncLogFactory
     * @param IdMappingInterfaceFactory $idMappingFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param LoggerInterface $logger
     * @param Config $config
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        GraphQlClientInterface $graphQlClient,
        IdMappingRepositoryInterface $idMappingRepository,
        SyncLogRepositoryInterface $syncLogRepository,
        SyncLogInterfaceFactory $syncLogFactory,
        IdMappingInterfaceFactory $idMappingFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        LoggerInterface $logger,
        Config $config,
        ResourceConnection $resourceConnection
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

        $this->config = $config;
        $this->resourceConnection = $resourceConnection;
    }

    /**
     * @inheritDoc
     */
    public function getEntityType(): string
    {
        return 'custom_table';
    }

    /**
     * @inheritDoc
     */
    public function getDependencies(): array
    {
        return [];
    }

    /**
     * Set the current table name for sync operations
     *
     * @param string $tableName
     * @return void
     */
    public function setCurrentTableName(string $tableName): void
    {
        $this->currentTableName = $tableName;
    }

    /**
     * Get the configured custom table names
     *
     * @return string[]
     */
    public function getCustomTableNames(): array
    {
        return $this->config->getCustomTableNames();
    }

    /**
     * @inheritDoc
     */
    public function fetchPage(int $page, int $pageSize, ?string $updatedSince = null): array
    {
        if ($this->currentTableName === null) {
            return [];
        }

        $query = $this->getGraphQlQuery();
        $data = $this->graphQlClient->query($query, [
            'tableName' => $this->currentTableName,
            'pageSize' => $pageSize,
            'currentPage' => $page,
        ]);

        $responseKey = $this->getResponseKey();

        if (!isset($data[$responseKey]['items'])) {
            return [];
        }

        $items = [];
        foreach ($data[$responseKey]['items'] as $row) {
            $decoded = json_decode($row['data'] ?? '{}', true);
            if (is_array($decoded)) {
                $items[] = $decoded;
            }
        }

        return $items;
    }

    /**
     * @inheritDoc
     */
    public function getSourceCount(?string $updatedSince = null): int
    {
        if ($this->currentTableName === null) {
            return 0;
        }

        $query = $this->getGraphQlQuery();
        $data = $this->graphQlClient->query($query, [
            'tableName' => $this->currentTableName,
            'pageSize' => 1,
            'currentPage' => 1,
        ]);

        $responseKey = $this->getResponseKey();

        return (int) ($data[$responseKey]['total_count'] ?? 0);
    }

    /**
     * @inheritDoc
     */
    protected function getGraphQlQuery(): string
    {
        return <<<'GRAPHQL'
query($tableName: String!, $pageSize: Int!, $currentPage: Int!) {
    magecloneCustomTableData(tableName: $tableName, pageSize: $pageSize, currentPage: $currentPage) {
        items {
            data
        }
        total_count
        page_info {
            page_size
            current_page
            total_pages
        }
        columns
    }
}
GRAPHQL;
    }

    /**
     * @inheritDoc
     */
    protected function getResponseKey(): string
    {
        return 'magecloneCustomTableData';
    }

    /**
     * @inheritDoc
     */
    protected function saveEntity(array $entityData): int
    {
        if ($this->currentTableName === null) {
            throw new \RuntimeException('Custom table name must be set before saving entities.');
        }

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName($this->currentTableName);

        if (!$connection->isTableExists($tableName)) {
            throw new \RuntimeException(
                sprintf('Destination table "%s" does not exist.', $tableName)
            );
        }

        $connection->insertOnDuplicate($tableName, $entityData);

        // Custom table rows may not have a single numeric ID; return 0
        return 0;
    }
}
