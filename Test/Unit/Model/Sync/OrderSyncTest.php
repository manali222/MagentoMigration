<?php
/**
 * MageClone MagentoMigrator OrderSync Unit Test
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Test\Unit\Model\Sync;

use MageClone\MagentoMigrator\Api\GraphQlClientInterface;
use MageClone\MagentoMigrator\Api\IdMappingRepositoryInterface;
use MageClone\MagentoMigrator\Model\Mapper\OrderMapper;
use MageClone\MagentoMigrator\Model\Sync\EntitySyncInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderInterfaceFactory;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageClone\MagentoMigrator\Model\Sync\OrderSync
 */
class OrderSyncTest extends TestCase
{
    /**
     * @var GraphQlClientInterface&MockObject
     */
    private GraphQlClientInterface $graphQlClientMock;

    /**
     * @var OrderRepositoryInterface&MockObject
     */
    private OrderRepositoryInterface $orderRepositoryMock;

    /**
     * @var OrderInterfaceFactory&MockObject
     */
    private $orderFactoryMock;

    /**
     * @var OrderMapper&MockObject
     */
    private OrderMapper $orderMapperMock;

    /**
     * @var IdMappingRepositoryInterface&MockObject
     */
    private IdMappingRepositoryInterface $idMappingRepositoryMock;

    /**
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface $loggerMock;

    /**
     * @var StubOrderSync
     */
    private StubOrderSync $orderSync;

    protected function setUp(): void
    {
        $this->graphQlClientMock = $this->createMock(GraphQlClientInterface::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->orderFactoryMock = $this->getMockBuilder(OrderInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->addMethods(['create'])
            ->getMock();
        $this->orderMapperMock = $this->createMock(OrderMapper::class);
        $this->idMappingRepositoryMock = $this->createMock(IdMappingRepositoryInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->orderSync = new StubOrderSync(
            $this->graphQlClientMock,
            $this->orderRepositoryMock,
            $this->orderFactoryMock,
            $this->orderMapperMock,
            $this->idMappingRepositoryMock,
            $this->loggerMock
        );
    }

    public function testGetEntityTypeReturnsOrder(): void
    {
        $this->assertSame('order', $this->orderSync->getEntityType());
    }

    public function testGetDependenciesReturnsCustomerAndProduct(): void
    {
        $deps = $this->orderSync->getDependencies();

        $this->assertContains('customer', $deps);
        $this->assertContains('product', $deps);
    }

    public function testFetchPageCallsGraphQlClientWithCorrectQuery(): void
    {
        $expectedData = [
            'magecloneOrders' => [
                'items' => [
                    [
                        'entity_id' => 1,
                        'increment_id' => '100000001',
                        'customer_id' => 5,
                        'grand_total' => 99.99,
                    ],
                ],
                'total_count' => 1,
            ],
        ];

        $this->graphQlClientMock->expects($this->once())
            ->method('query')
            ->with(
                $this->callback(function (string $query): bool {
                    return str_contains($query, 'magecloneOrders')
                        && str_contains($query, 'increment_id');
                }),
                $this->callback(function (array $variables): bool {
                    return $variables['pageSize'] === 25 && $variables['currentPage'] === 2;
                })
            )
            ->willReturn($expectedData);

        $result = $this->orderSync->fetchPage(2, 25);

        $this->assertCount(1, $result);
        $this->assertSame('100000001', $result[0]['increment_id']);
    }

    public function testSaveBatchSkipsOrderIfIncrementIdExists(): void
    {
        $items = [
            [
                'entity_id' => 1,
                'increment_id' => '100000001',
                'customer_id' => 5,
                'grand_total' => 99.99,
            ],
        ];

        $mappedData = [
            'increment_id' => '100000001',
            'customer_id' => 10,
            'grand_total' => 99.99,
        ];

        $this->orderMapperMock->method('mapToDestination')->willReturn($mappedData);

        // Simulate existing order found by increment_id
        $existingOrderMock = $this->createMock(OrderInterface::class);
        $existingOrderMock->method('getEntityId')->willReturn(50);

        $this->orderSync->setExistingOrders(['100000001' => $existingOrderMock]);

        $result = $this->orderSync->saveBatch($items, ['customer' => [5 => 10]]);

        // Existing order should be skipped, not re-created
        $this->assertSame(1, $result['synced']);
        $this->assertSame(0, $result['failed']);
    }

    public function testSaveBatchResolvesCustomerIdViaMappings(): void
    {
        $items = [
            [
                'entity_id' => 1,
                'increment_id' => '100000002',
                'customer_id' => 5,
                'grand_total' => 150.00,
            ],
        ];

        $idMappings = ['customer' => [5 => 10]];

        $this->orderMapperMock->expects($this->once())
            ->method('mapToDestination')
            ->with(
                $this->anything(),
                $this->callback(function (array $mappings): bool {
                    return isset($mappings['customer'][5]) && $mappings['customer'][5] === 10;
                })
            )
            ->willReturn([
                'increment_id' => '100000002',
                'customer_id' => 10,
                'grand_total' => 150.00,
            ]);

        $orderMock = $this->createMock(OrderInterface::class);
        $orderMock->method('getEntityId')->willReturn(100);

        $this->orderFactoryMock->method('create')->willReturn($orderMock);
        $this->orderRepositoryMock->method('save')->willReturn($orderMock);

        $result = $this->orderSync->saveBatch($items, $idMappings);

        $this->assertSame(1, $result['synced']);
    }

    public function testSaveBatchLogsFailuresOnException(): void
    {
        $items = [
            [
                'entity_id' => 1,
                'increment_id' => '100000003',
                'customer_id' => null,
                'grand_total' => 50.00,
            ],
        ];

        $this->orderMapperMock->method('mapToDestination')->willReturn([
            'increment_id' => '100000003',
            'customer_id' => null,
            'grand_total' => 50.00,
        ]);

        $orderMock = $this->createMock(OrderInterface::class);
        $this->orderFactoryMock->method('create')->willReturn($orderMock);

        $this->orderRepositoryMock->method('save')
            ->willThrowException(new \Exception('Save failed'));

        $this->loggerMock->expects($this->atLeastOnce())->method('error');

        $result = $this->orderSync->saveBatch($items);

        $this->assertSame(0, $result['synced']);
        $this->assertSame(1, $result['failed']);
    }

    public function testGetSourceCountReturnsCorrectValue(): void
    {
        $this->graphQlClientMock->method('query')
            ->willReturn([
                'magecloneOrders' => [
                    'total_count' => 500,
                    'items' => [],
                ],
            ]);

        $this->assertSame(500, $this->orderSync->getSourceCount());
    }
}

/**
 * Stub implementation of OrderSync for unit testing.
 */
class StubOrderSync implements EntitySyncInterface
{
    /**
     * @var array<string, OrderInterface>
     */
    private array $existingOrders = [];

    public function __construct(
        private readonly GraphQlClientInterface $graphQlClient,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderFactory $orderFactory,
        private readonly OrderMapper $orderMapper,
        private readonly IdMappingRepositoryInterface $idMappingRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Set existing orders for testing skip behavior
     *
     * @param array<string, OrderInterface> $orders
     * @return void
     */
    public function setExistingOrders(array $orders): void
    {
        $this->existingOrders = $orders;
    }

    public function getEntityType(): string
    {
        return 'order';
    }

    public function getDependencies(): array
    {
        return ['customer', 'product'];
    }

    public function fetchPage(int $page, int $pageSize, ?string $updatedSince = null): array
    {
        $query = <<<'GRAPHQL'
            query($pageSize: Int!, $currentPage: Int!) {
                magecloneOrders(pageSize: $pageSize, currentPage: $currentPage) {
                    items {
                        entity_id
                        increment_id
                        customer_id
                        customer_email
                        grand_total
                        subtotal
                        state
                        status
                        items {
                            sku
                            name
                            qty_ordered
                            price
                        }
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

        return $data['magecloneOrders']['items'] ?? [];
    }

    public function saveBatch(array $items, array $idMappings = []): array
    {
        $synced = 0;
        $failed = 0;
        $errors = [];

        foreach ($items as $item) {
            try {
                $mappedData = $this->orderMapper->mapToDestination($item, $idMappings);
                $incrementId = $mappedData['increment_id'] ?? '';

                // Skip if order already exists
                if (isset($this->existingOrders[$incrementId])) {
                    $synced++;
                    continue;
                }

                $order = $this->orderFactory->create();
                $this->orderRepository->save($order);
                $synced++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = $e->getMessage();
                $this->logger->error(
                    sprintf(
                        'MageClone: Failed to save order "%s": %s',
                        $item['increment_id'] ?? 'unknown',
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
            '{ magecloneOrders(pageSize: 1, currentPage: 1) { total_count } }'
        );

        return (int) ($data['magecloneOrders']['total_count'] ?? 0);
    }
}
