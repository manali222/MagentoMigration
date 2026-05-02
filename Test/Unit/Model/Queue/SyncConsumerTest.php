<?php
/**
 * MageClone MagentoMigrator SyncConsumer Unit Test
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Test\Unit\Model\Queue;

use MageClone\MagentoMigrator\Api\Data\SyncStatusInterface;
use MageClone\MagentoMigrator\Api\SyncStatusRepositoryInterface;
use MageClone\MagentoMigrator\Model\Config;
use MageClone\MagentoMigrator\Model\Queue\SyncConsumer;
use MageClone\MagentoMigrator\Model\Queue\SyncMessage;
use MageClone\MagentoMigrator\Model\Sync\EntitySyncInterface;
use MageClone\MagentoMigrator\Model\SyncStatusFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \MageClone\MagentoMigrator\Model\Queue\SyncConsumer
 */
class SyncConsumerTest extends TestCase
{
    /**
     * @var EntitySyncInterface&MockObject
     */
    private EntitySyncInterface $entitySyncMock;

    /**
     * @var SyncStatusRepositoryInterface&MockObject
     */
    private SyncStatusRepositoryInterface $syncStatusRepositoryMock;

    /**
     * @var SyncStatusFactory&MockObject
     */
    private $syncStatusFactoryMock;

    /**
     * @var LoggerInterface&MockObject
     */
    private LoggerInterface $loggerMock;

    /**
     * @var Config&MockObject
     */
    private Config $configMock;

    /**
     * @var SyncConsumer
     */
    private SyncConsumer $consumer;

    protected function setUp(): void
    {
        $this->entitySyncMock = $this->createMock(EntitySyncInterface::class);
        $this->syncStatusRepositoryMock = $this->createMock(SyncStatusRepositoryInterface::class);
        $this->syncStatusFactoryMock = $this->getMockBuilder(SyncStatusFactory::class)
            ->disableOriginalConstructor()
            ->addMethods(['create'])
            ->getMock();
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->configMock = $this->createMock(Config::class);

        $this->consumer = new SyncConsumer(
            ['customer' => $this->entitySyncMock],
            $this->syncStatusRepositoryMock,
            $this->syncStatusFactoryMock,
            $this->loggerMock,
            $this->configMock
        );
    }

    /**
     * @return SyncMessage
     */
    private function createSyncMessage(
        string $entityType = 'customer',
        int $page = 1,
        int $pageSize = 50
    ): SyncMessage {
        $message = new SyncMessage();
        $message->setEntityType($entityType);
        $message->setPage($page);
        $message->setPageSize($pageSize);
        $message->setBatchId('test-batch-123');
        return $message;
    }

    /**
     * @return SyncStatusInterface&MockObject
     */
    private function createSyncStatusMock(): SyncStatusInterface
    {
        $syncStatus = $this->createMock(SyncStatusInterface::class);
        $syncStatus->method('getSyncedCount')->willReturn(0);
        $syncStatus->method('getFailedCount')->willReturn(0);
        $syncStatus->method('getPendingCount')->willReturn(100);
        $syncStatus->method('getSourceCount')->willReturn(100);
        $syncStatus->method('setSyncedCount')->willReturnSelf();
        $syncStatus->method('setFailedCount')->willReturnSelf();
        $syncStatus->method('setPendingCount')->willReturnSelf();
        $syncStatus->method('setStatus')->willReturnSelf();
        $syncStatus->method('setLastSyncedAt')->willReturnSelf();
        $syncStatus->method('setEntityType')->willReturnSelf();
        $syncStatus->method('setSourceCount')->willReturnSelf();
        $syncStatus->method('setDestinationCount')->willReturnSelf();

        return $syncStatus;
    }

    public function testProcessCallsCorrectEntitySync(): void
    {
        $message = $this->createSyncMessage();
        $syncStatus = $this->createSyncStatusMock();

        $this->syncStatusRepositoryMock->method('getByEntityType')
            ->willReturn($syncStatus);
        $this->syncStatusRepositoryMock->method('save')
            ->willReturn($syncStatus);

        $this->entitySyncMock->expects($this->once())
            ->method('fetchPage')
            ->with(1, 50, null)
            ->willReturn([
                ['entity_id' => 1, 'email' => 'test@example.com'],
            ]);

        $this->entitySyncMock->expects($this->once())
            ->method('saveBatch')
            ->willReturn(['synced' => 1, 'failed' => 0, 'errors' => []]);

        $this->consumer->process($message);
    }

    public function testProcessUpdatesStatusCounters(): void
    {
        $message = $this->createSyncMessage();
        $syncStatus = $this->createSyncStatusMock();

        $this->syncStatusRepositoryMock->method('getByEntityType')
            ->willReturn($syncStatus);

        $syncStatus->expects($this->atLeastOnce())
            ->method('setSyncedCount');
        $syncStatus->expects($this->atLeastOnce())
            ->method('setFailedCount');

        $this->syncStatusRepositoryMock->expects($this->atLeastOnce())
            ->method('save')
            ->with($syncStatus)
            ->willReturn($syncStatus);

        $this->entitySyncMock->method('fetchPage')
            ->willReturn([['entity_id' => 1]]);
        $this->entitySyncMock->method('saveBatch')
            ->willReturn(['synced' => 5, 'failed' => 2, 'errors' => ['err1', 'err2']]);

        $this->consumer->process($message);
    }

    public function testProcessHandlesExceptionAndSetsFailedStatus(): void
    {
        $message = $this->createSyncMessage();
        $syncStatus = $this->createSyncStatusMock();

        $this->syncStatusRepositoryMock->method('getByEntityType')
            ->willReturn($syncStatus);
        $this->syncStatusRepositoryMock->method('save')
            ->willReturn($syncStatus);

        $this->entitySyncMock->method('fetchPage')
            ->willThrowException(new \RuntimeException('Connection lost'));

        $syncStatus->expects($this->atLeastOnce())
            ->method('setStatus')
            ->with(SyncStatusInterface::STATUS_FAILED);

        $this->loggerMock->expects($this->atLeastOnce())
            ->method('error');

        $this->consumer->process($message);
    }

    public function testProcessLogsErrorForUnknownEntityType(): void
    {
        $message = $this->createSyncMessage('unknown_entity');

        $syncStatus = $this->createSyncStatusMock();
        $this->syncStatusRepositoryMock->method('getByEntityType')
            ->willReturn($syncStatus);
        $this->syncStatusRepositoryMock->method('save')
            ->willReturn($syncStatus);

        $this->loggerMock->expects($this->atLeastOnce())
            ->method('error')
            ->with($this->stringContains('No entity sync handler'));

        $this->consumer->process($message);
    }

    public function testProcessCreatesNewSyncStatusWhenNotFound(): void
    {
        $message = $this->createSyncMessage();
        $syncStatus = $this->createSyncStatusMock();

        $this->syncStatusRepositoryMock->method('getByEntityType')
            ->willThrowException(new NoSuchEntityException(__('Not found')));

        $this->syncStatusFactoryMock->expects($this->atLeastOnce())
            ->method('create')
            ->willReturn($syncStatus);

        $this->syncStatusRepositoryMock->method('save')
            ->willReturn($syncStatus);

        $this->entitySyncMock->method('fetchPage')->willReturn([]);
        $this->entitySyncMock->method('saveBatch')
            ->willReturn(['synced' => 0, 'failed' => 0, 'errors' => []]);

        $this->consumer->process($message);
    }
}
