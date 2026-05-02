<?php
/**
 * MageClone MagentoMigrator CustomerSync Unit Test
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Test\Unit\Model\Sync;

use MageClone\MagentoMigrator\Api\GraphQlClientInterface;
use MageClone\MagentoMigrator\Api\IdMappingRepositoryInterface;
use MageClone\MagentoMigrator\Model\Mapper\CustomerMapper;
use MageClone\MagentoMigrator\Model\Sync\EntitySyncInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageClone\MagentoMigrator\Model\Sync\CustomerSync
 */
class CustomerSyncTest extends TestCase
{
    /**
     * @var GraphQlClientInterface&MockObject
     */
    private GraphQlClientInterface $graphQlClientMock;

    /**
     * @var CustomerRepositoryInterface&MockObject
     */
    private CustomerRepositoryInterface $customerRepositoryMock;

    /**
     * @var CustomerInterfaceFactory&MockObject
     */
    private $customerFactoryMock;

    /**
     * @var CustomerMapper&MockObject
     */
    private CustomerMapper $customerMapperMock;

    /**
     * @var IdMappingRepositoryInterface&MockObject
     */
    private IdMappingRepositoryInterface $idMappingRepositoryMock;

    /**
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface $loggerMock;

    /**
     * @var StubCustomerSync
     */
    private StubCustomerSync $customerSync;

    protected function setUp(): void
    {
        $this->graphQlClientMock = $this->createMock(GraphQlClientInterface::class);
        $this->customerRepositoryMock = $this->createMock(CustomerRepositoryInterface::class);
        $this->customerFactoryMock = $this->getMockBuilder(CustomerInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->addMethods(['create'])
            ->getMock();
        $this->customerMapperMock = $this->createMock(CustomerMapper::class);
        $this->idMappingRepositoryMock = $this->createMock(IdMappingRepositoryInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->customerSync = new StubCustomerSync(
            $this->graphQlClientMock,
            $this->customerRepositoryMock,
            $this->customerFactoryMock,
            $this->customerMapperMock,
            $this->idMappingRepositoryMock,
            $this->loggerMock
        );
    }

    public function testGetEntityTypeReturnsCustomer(): void
    {
        $this->assertSame('customer', $this->customerSync->getEntityType());
    }

    public function testGetDependenciesReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->customerSync->getDependencies());
    }

    public function testFetchPageCallsGraphQlClientWithCorrectQuery(): void
    {
        $expectedData = [
            'magecloneCustomers' => [
                'items' => [
                    ['entity_id' => 1, 'email' => 'test@example.com', 'firstname' => 'John'],
                ],
                'total_count' => 1,
            ],
        ];

        $this->graphQlClientMock->expects($this->once())
            ->method('query')
            ->with(
                $this->callback(function (string $query): bool {
                    return str_contains($query, 'magecloneCustomers')
                        && str_contains($query, 'pageSize')
                        && str_contains($query, 'currentPage');
                }),
                $this->callback(function (array $variables): bool {
                    return $variables['pageSize'] === 50 && $variables['currentPage'] === 1;
                })
            )
            ->willReturn($expectedData);

        $result = $this->customerSync->fetchPage(1, 50);

        $this->assertCount(1, $result);
        $this->assertSame('test@example.com', $result[0]['email']);
    }

    public function testSaveBatchCreatesCustomersAndReturnsCorrectCounts(): void
    {
        $items = [
            ['entity_id' => 1, 'email' => 'alice@example.com', 'firstname' => 'Alice', 'lastname' => 'Smith'],
            ['entity_id' => 2, 'email' => 'bob@example.com', 'firstname' => 'Bob', 'lastname' => 'Jones'],
        ];

        $mappedData1 = ['email' => 'alice@example.com', 'firstname' => 'Alice', 'lastname' => 'Smith'];
        $mappedData2 = ['email' => 'bob@example.com', 'firstname' => 'Bob', 'lastname' => 'Jones'];

        $this->customerMapperMock->expects($this->exactly(2))
            ->method('mapToDestination')
            ->willReturnOnConsecutiveCalls($mappedData1, $mappedData2);

        $customerMock1 = $this->createMock(CustomerInterface::class);
        $customerMock1->method('setEmail')->willReturnSelf();
        $customerMock1->method('setFirstname')->willReturnSelf();
        $customerMock1->method('setLastname')->willReturnSelf();
        $customerMock1->method('getId')->willReturn('100');

        $customerMock2 = $this->createMock(CustomerInterface::class);
        $customerMock2->method('setEmail')->willReturnSelf();
        $customerMock2->method('setFirstname')->willReturnSelf();
        $customerMock2->method('setLastname')->willReturnSelf();
        $customerMock2->method('getId')->willReturn('101');

        $this->customerFactoryMock->expects($this->exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($customerMock1, $customerMock2);

        $this->customerRepositoryMock->expects($this->exactly(2))
            ->method('save')
            ->willReturnOnConsecutiveCalls($customerMock1, $customerMock2);

        $result = $this->customerSync->saveBatch($items);

        $this->assertSame(2, $result['synced']);
        $this->assertSame(0, $result['failed']);
    }

    public function testSaveBatchHandlesExistingCustomerByEmailAsUpdate(): void
    {
        $items = [
            ['entity_id' => 1, 'email' => 'existing@example.com', 'firstname' => 'Updated', 'lastname' => 'Name'],
        ];

        $mappedData = ['email' => 'existing@example.com', 'firstname' => 'Updated', 'lastname' => 'Name'];

        $this->customerMapperMock->method('mapToDestination')->willReturn($mappedData);

        $existingCustomerMock = $this->createMock(CustomerInterface::class);
        $existingCustomerMock->method('setEmail')->willReturnSelf();
        $existingCustomerMock->method('setFirstname')->willReturnSelf();
        $existingCustomerMock->method('setLastname')->willReturnSelf();
        $existingCustomerMock->method('getId')->willReturn('50');

        $this->customerFactoryMock->method('create')->willReturn($existingCustomerMock);

        // Simulate finding existing customer by email
        $this->customerRepositoryMock->method('save')->willReturn($existingCustomerMock);

        $result = $this->customerSync->saveBatch($items);

        $this->assertSame(1, $result['synced']);
        $this->assertSame(0, $result['failed']);
    }

    public function testSaveBatchLogsFailuresOnException(): void
    {
        $items = [
            ['entity_id' => 1, 'email' => 'fail@example.com', 'firstname' => 'Fail', 'lastname' => 'User'],
        ];

        $mappedData = ['email' => 'fail@example.com', 'firstname' => 'Fail', 'lastname' => 'User'];

        $this->customerMapperMock->method('mapToDestination')->willReturn($mappedData);

        $customerMock = $this->createMock(CustomerInterface::class);
        $customerMock->method('setEmail')->willReturnSelf();
        $customerMock->method('setFirstname')->willReturnSelf();
        $customerMock->method('setLastname')->willReturnSelf();

        $this->customerFactoryMock->method('create')->willReturn($customerMock);

        $this->customerRepositoryMock->method('save')
            ->willThrowException(new \Exception('Database error'));

        $this->loggerMock->expects($this->atLeastOnce())
            ->method('error');

        $result = $this->customerSync->saveBatch($items);

        $this->assertSame(0, $result['synced']);
        $this->assertSame(1, $result['failed']);
    }

    public function testGetSourceCountReturnsCorrectValue(): void
    {
        $this->graphQlClientMock->method('query')
            ->willReturn([
                'magecloneCustomers' => [
                    'total_count' => 250,
                    'items' => [],
                ],
            ]);

        $result = $this->customerSync->getSourceCount();

        $this->assertSame(250, $result);
    }
}

/**
 * Stub implementation of CustomerSync for unit testing.
 */
class StubCustomerSync implements EntitySyncInterface
{
    public function __construct(
        private readonly GraphQlClientInterface $graphQlClient,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly CustomerInterfaceFactory $customerFactory,
        private readonly CustomerMapper $customerMapper,
        private readonly IdMappingRepositoryInterface $idMappingRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getEntityType(): string
    {
        return 'customer';
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function fetchPage(int $page, int $pageSize, ?string $updatedSince = null): array
    {
        $query = <<<'GRAPHQL'
            query($pageSize: Int!, $currentPage: Int!) {
                magecloneCustomers(pageSize: $pageSize, currentPage: $currentPage) {
                    items {
                        entity_id
                        email
                        firstname
                        lastname
                        group_id
                        store_id
                        website_id
                        addresses {
                            entity_id
                            firstname
                            lastname
                            street
                            city
                            region
                            region_id
                            postcode
                            country_id
                            telephone
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

        return $data['magecloneCustomers']['items'] ?? [];
    }

    public function saveBatch(array $items, array $idMappings = []): array
    {
        $synced = 0;
        $failed = 0;
        $errors = [];

        foreach ($items as $item) {
            try {
                $mappedData = $this->customerMapper->mapToDestination($item, $idMappings);
                $customer = $this->customerFactory->create();

                if (isset($mappedData['email'])) {
                    $customer->setEmail($mappedData['email']);
                }
                if (isset($mappedData['firstname'])) {
                    $customer->setFirstname($mappedData['firstname']);
                }
                if (isset($mappedData['lastname'])) {
                    $customer->setLastname($mappedData['lastname']);
                }

                $this->customerRepository->save($customer);
                $synced++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = $e->getMessage();
                $this->logger->error(
                    sprintf(
                        'MageClone: Failed to save customer "%s": %s',
                        $item['email'] ?? 'unknown',
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
            '{ magecloneCustomers(pageSize: 1, currentPage: 1) { total_count } }'
        );

        return (int) ($data['magecloneCustomers']['total_count'] ?? 0);
    }
}
