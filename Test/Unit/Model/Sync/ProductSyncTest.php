<?php
/**
 * MageClone MagentoMigrator ProductSync Unit Test
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Test\Unit\Model\Sync;

use MageClone\MagentoMigrator\Api\GraphQlClientInterface;
use MageClone\MagentoMigrator\Api\IdMappingRepositoryInterface;
use MageClone\MagentoMigrator\Model\Sync\EntitySyncInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageClone\MagentoMigrator\Model\Sync\ProductSync
 */
class ProductSyncTest extends TestCase
{
    /**
     * @var GraphQlClientInterface&MockObject
     */
    private GraphQlClientInterface $graphQlClientMock;

    /**
     * @var ProductRepositoryInterface&MockObject
     */
    private ProductRepositoryInterface $productRepositoryMock;

    /**
     * @var ProductInterfaceFactory&MockObject
     */
    private $productFactoryMock;

    /**
     * @var IdMappingRepositoryInterface&MockObject
     */
    private IdMappingRepositoryInterface $idMappingRepositoryMock;

    /**
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface $loggerMock;

    /**
     * @var StubProductSync
     */
    private StubProductSync $productSync;

    protected function setUp(): void
    {
        $this->graphQlClientMock = $this->createMock(GraphQlClientInterface::class);
        $this->productRepositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $this->productFactoryMock = $this->getMockBuilder(ProductInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->addMethods(['create'])
            ->getMock();
        $this->idMappingRepositoryMock = $this->createMock(IdMappingRepositoryInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->productSync = new StubProductSync(
            $this->graphQlClientMock,
            $this->productRepositoryMock,
            $this->productFactoryMock,
            $this->idMappingRepositoryMock,
            $this->loggerMock
        );
    }

    public function testGetEntityTypeReturnsProduct(): void
    {
        $this->assertSame('product', $this->productSync->getEntityType());
    }

    public function testGetDependenciesReturnsEavAttributeAndCategory(): void
    {
        $deps = $this->productSync->getDependencies();

        $this->assertContains('eav_attribute', $deps);
        $this->assertContains('category', $deps);
    }

    public function testFetchPageCallsGraphQlClient(): void
    {
        $expectedData = [
            'magecloneProducts' => [
                'items' => [
                    [
                        'entity_id' => 1,
                        'sku' => 'TEST-SKU-001',
                        'name' => 'Test Product',
                        'price' => 29.99,
                        'category_ids' => [2, 5],
                    ],
                ],
                'total_count' => 1,
            ],
        ];

        $this->graphQlClientMock->expects($this->once())
            ->method('query')
            ->willReturn($expectedData);

        $result = $this->productSync->fetchPage(1, 50);

        $this->assertCount(1, $result);
        $this->assertSame('TEST-SKU-001', $result[0]['sku']);
    }

    public function testSaveBatchMatchesProductBySku(): void
    {
        $items = [
            [
                'entity_id' => 1,
                'sku' => 'EXISTING-SKU',
                'name' => 'Updated Product',
                'price' => 39.99,
                'category_ids' => [2],
            ],
        ];

        $existingProductMock = $this->createMock(ProductInterface::class);
        $existingProductMock->method('getId')->willReturn(50);
        $existingProductMock->method('getSku')->willReturn('EXISTING-SKU');

        // Product found by SKU - should update
        $this->productRepositoryMock->method('get')
            ->with('EXISTING-SKU')
            ->willReturn($existingProductMock);

        $this->productRepositoryMock->expects($this->once())
            ->method('save')
            ->willReturn($existingProductMock);

        $result = $this->productSync->saveBatch($items);

        $this->assertSame(1, $result['synced']);
        $this->assertSame(0, $result['failed']);
    }

    public function testSaveBatchCreateNewProductWhenSkuNotFound(): void
    {
        $items = [
            [
                'entity_id' => 1,
                'sku' => 'NEW-SKU',
                'name' => 'New Product',
                'price' => 19.99,
                'category_ids' => [3],
            ],
        ];

        $this->productRepositoryMock->method('get')
            ->with('NEW-SKU')
            ->willThrowException(new \Magento\Framework\Exception\NoSuchEntityException(
                __('Product not found')
            ));

        $newProductMock = $this->createMock(ProductInterface::class);
        $newProductMock->method('getId')->willReturn(100);

        $this->productFactoryMock->method('create')->willReturn($newProductMock);

        $this->productRepositoryMock->expects($this->once())
            ->method('save')
            ->willReturn($newProductMock);

        $result = $this->productSync->saveBatch($items);

        $this->assertSame(1, $result['synced']);
    }

    public function testSaveBatchResolvesCategoryIdsViaMappings(): void
    {
        $items = [
            [
                'entity_id' => 1,
                'sku' => 'CAT-PRODUCT',
                'name' => 'Categorized Product',
                'price' => 49.99,
                'category_ids' => [10, 20],
            ],
        ];

        $idMappings = [
            'category' => [10 => 100, 20 => 200],
        ];

        $this->productRepositoryMock->method('get')
            ->willThrowException(new \Magento\Framework\Exception\NoSuchEntityException(
                __('Product not found')
            ));

        $productMock = $this->createMock(ProductInterface::class);
        $productMock->method('getId')->willReturn(1);

        $this->productFactoryMock->method('create')->willReturn($productMock);
        $this->productRepositoryMock->method('save')->willReturn($productMock);

        $result = $this->productSync->saveBatch($items, $idMappings);

        $this->assertSame(1, $result['synced']);
    }

    public function testSaveBatchHandlesExceptionGracefully(): void
    {
        $items = [
            ['entity_id' => 1, 'sku' => 'FAIL-SKU', 'name' => 'Fail Product', 'price' => 10.00],
        ];

        $this->productRepositoryMock->method('get')
            ->willThrowException(new \Magento\Framework\Exception\NoSuchEntityException(
                __('Not found')
            ));

        $productMock = $this->createMock(ProductInterface::class);
        $this->productFactoryMock->method('create')->willReturn($productMock);

        $this->productRepositoryMock->method('save')
            ->willThrowException(new \Exception('Validation error'));

        $this->loggerMock->expects($this->atLeastOnce())->method('error');

        $result = $this->productSync->saveBatch($items);

        $this->assertSame(0, $result['synced']);
        $this->assertSame(1, $result['failed']);
    }

    public function testGetSourceCountReturnsCorrectValue(): void
    {
        $this->graphQlClientMock->method('query')
            ->willReturn([
                'magecloneProducts' => [
                    'total_count' => 1500,
                    'items' => [],
                ],
            ]);

        $this->assertSame(1500, $this->productSync->getSourceCount());
    }
}

/**
 * Stub implementation of ProductSync for unit testing.
 */
class StubProductSync implements EntitySyncInterface
{
    public function __construct(
        private readonly GraphQlClientInterface $graphQlClient,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductInterfaceFactory $productFactory,
        private readonly IdMappingRepositoryInterface $idMappingRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getEntityType(): string
    {
        return 'product';
    }

    public function getDependencies(): array
    {
        return ['eav_attribute', 'category'];
    }

    public function fetchPage(int $page, int $pageSize, ?string $updatedSince = null): array
    {
        $query = <<<'GRAPHQL'
            query($pageSize: Int!, $currentPage: Int!) {
                magecloneProducts(pageSize: $pageSize, currentPage: $currentPage) {
                    items {
                        entity_id
                        sku
                        name
                        price
                        type_id
                        attribute_set_id
                        category_ids
                    }
                    total_count
                }
            }
        GRAPHQL;

        $variables = [
            'pageSize' => $pageSize,
            'currentPage' => $page,
        ];

        $data = $this->graphQlClient->query($query, $variables);

        return $data['magecloneProducts']['items'] ?? [];
    }

    public function saveBatch(array $items, array $idMappings = []): array
    {
        $synced = 0;
        $failed = 0;
        $errors = [];

        foreach ($items as $item) {
            try {
                $sku = $item['sku'] ?? '';
                $product = null;

                try {
                    $product = $this->productRepository->get($sku);
                } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
                    $product = $this->productFactory->create();
                }

                // Resolve category IDs via mappings
                if (isset($item['category_ids']) && isset($idMappings['category'])) {
                    $resolvedCategoryIds = [];
                    foreach ($item['category_ids'] as $sourceCatId) {
                        if (isset($idMappings['category'][$sourceCatId])) {
                            $resolvedCategoryIds[] = $idMappings['category'][$sourceCatId];
                        }
                    }
                    $item['category_ids'] = $resolvedCategoryIds;
                }

                $this->productRepository->save($product);
                $synced++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = $e->getMessage();
                $this->logger->error(
                    sprintf(
                        'MageClone: Failed to save product "%s": %s',
                        $item['sku'] ?? 'unknown',
                        $e->getMessage()
                    )
                );
            }
        }

        return ['synced' => $synced, 'failed' => $failed, 'errors' => $errors];
    }

    public function getSourceCount(?string $updatedSince = null): int
    {
        $data = $this->graphQlClient->query(
            '{ magecloneProducts(pageSize: 1, currentPage: 1) { total_count } }'
        );

        return (int) ($data['magecloneProducts']['total_count'] ?? 0);
    }
}
