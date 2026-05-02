<?php
/**
 * MageClone MagentoMigrator IdMappingRepository Unit Test
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Test\Unit\Model\Repository;

use MageClone\MagentoMigrator\Api\Data\IdMappingInterface;
use MageClone\MagentoMigrator\Model\IdMapping;
use MageClone\MagentoMigrator\Model\IdMappingFactory;
use MageClone\MagentoMigrator\Model\Repository\IdMappingRepository;
use MageClone\MagentoMigrator\Model\ResourceModel\IdMapping as IdMappingResource;
use MageClone\MagentoMigrator\Model\ResourceModel\IdMapping\Collection;
use MageClone\MagentoMigrator\Model\ResourceModel\IdMapping\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageClone\MagentoMigrator\Model\Repository\IdMappingRepository
 */
class IdMappingRepositoryTest extends TestCase
{
    /**
     * @var IdMappingResource&MockObject
     */
    private IdMappingResource $resourceMock;

    /**
     * @var IdMappingFactory&MockObject
     */
    private $idMappingFactoryMock;

    /**
     * @var CollectionFactory&MockObject
     */
    private $collectionFactoryMock;

    /**
     * @var CollectionProcessorInterface&MockObject
     */
    private CollectionProcessorInterface $collectionProcessorMock;

    /**
     * @var SearchResultsInterfaceFactory&MockObject
     */
    private $searchResultsFactoryMock;

    /**
     * @var IdMappingRepository
     */
    private IdMappingRepository $repository;

    protected function setUp(): void
    {
        $this->resourceMock = $this->createMock(IdMappingResource::class);
        $this->idMappingFactoryMock = $this->getMockBuilder(IdMappingFactory::class)
            ->disableOriginalConstructor()
            ->addMethods(['create'])
            ->getMock();
        $this->collectionFactoryMock = $this->getMockBuilder(CollectionFactory::class)
            ->disableOriginalConstructor()
            ->addMethods(['create'])
            ->getMock();
        $this->collectionProcessorMock = $this->createMock(CollectionProcessorInterface::class);
        $this->searchResultsFactoryMock = $this->getMockBuilder(SearchResultsInterfaceFactory::class)
            ->disableOriginalConstructor()
            ->addMethods(['create'])
            ->getMock();

        $this->repository = new IdMappingRepository(
            $this->resourceMock,
            $this->idMappingFactoryMock,
            $this->collectionFactoryMock,
            $this->collectionProcessorMock,
            $this->searchResultsFactoryMock
        );
    }

    public function testSavePersistsModel(): void
    {
        $idMappingMock = $this->createMock(IdMapping::class);

        $this->resourceMock->expects($this->once())
            ->method('save')
            ->with($idMappingMock);

        $result = $this->repository->save($idMappingMock);

        $this->assertSame($idMappingMock, $result);
    }

    public function testSaveThrowsCouldNotSaveExceptionOnFailure(): void
    {
        $idMappingMock = $this->createMock(IdMapping::class);

        $this->resourceMock->method('save')
            ->willThrowException(new \Exception('Database error'));

        $this->expectException(CouldNotSaveException::class);

        $this->repository->save($idMappingMock);
    }

    public function testGetBySourceIdLoadsCorrectRecord(): void
    {
        $idMappingMock = $this->createMock(IdMapping::class);
        $idMappingMock->method('getMappingId')->willReturn(1);

        $collectionMock = $this->createMock(Collection::class);
        $collectionMock->expects($this->exactly(2))
            ->method('addFieldToFilter')
            ->willReturnCallback(function (string $field, $value) use ($collectionMock): Collection {
                static $callCount = 0;
                $callCount++;
                if ($callCount === 1) {
                    $this->assertSame(IdMappingInterface::ENTITY_TYPE, $field);
                    $this->assertSame('customer', $value);
                } elseif ($callCount === 2) {
                    $this->assertSame(IdMappingInterface::SOURCE_ID, $field);
                    $this->assertSame(42, $value);
                }
                return $collectionMock;
            });
        $collectionMock->method('setPageSize')->willReturnSelf();
        $collectionMock->method('getFirstItem')->willReturn($idMappingMock);

        $this->collectionFactoryMock->method('create')->willReturn($collectionMock);

        $result = $this->repository->getBySourceId('customer', 42);

        $this->assertSame($idMappingMock, $result);
    }

    public function testGetBySourceIdThrowsNoSuchEntityExceptionWhenNotFound(): void
    {
        $idMappingMock = $this->createMock(IdMapping::class);
        $idMappingMock->method('getMappingId')->willReturn(null);

        $collectionMock = $this->createMock(Collection::class);
        $collectionMock->method('addFieldToFilter')->willReturnSelf();
        $collectionMock->method('setPageSize')->willReturnSelf();
        $collectionMock->method('getFirstItem')->willReturn($idMappingMock);

        $this->collectionFactoryMock->method('create')->willReturn($collectionMock);

        $this->expectException(NoSuchEntityException::class);

        $this->repository->getBySourceId('customer', 999);
    }

    public function testGetByIdLoadsCorrectRecord(): void
    {
        $idMappingMock = $this->createMock(IdMapping::class);
        $idMappingMock->method('getMappingId')->willReturn(5);

        $this->idMappingFactoryMock->method('create')->willReturn($idMappingMock);

        $this->resourceMock->expects($this->once())
            ->method('load')
            ->with($idMappingMock, 5);

        $result = $this->repository->getById(5);

        $this->assertSame($idMappingMock, $result);
    }

    public function testGetByIdThrowsNoSuchEntityExceptionWhenNotFound(): void
    {
        $idMappingMock = $this->createMock(IdMapping::class);
        $idMappingMock->method('getMappingId')->willReturn(null);

        $this->idMappingFactoryMock->method('create')->willReturn($idMappingMock);

        $this->expectException(NoSuchEntityException::class);

        $this->repository->getById(999);
    }

    public function testDeleteRemovesRecord(): void
    {
        $idMappingMock = $this->createMock(IdMapping::class);

        $this->resourceMock->expects($this->once())
            ->method('delete')
            ->with($idMappingMock);

        $result = $this->repository->delete($idMappingMock);

        $this->assertTrue($result);
    }
}
