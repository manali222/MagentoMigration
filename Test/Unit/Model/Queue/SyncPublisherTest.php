<?php
/**
 * MageClone MagentoMigrator SyncPublisher Unit Test
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Test\Unit\Model\Queue;

use MageClone\MagentoMigrator\Model\Queue\SyncMessage;
use MageClone\MagentoMigrator\Model\Queue\SyncPublisher;
use Magento\Framework\MessageQueue\PublisherInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageClone\MagentoMigrator\Model\Queue\SyncPublisher
 */
class SyncPublisherTest extends TestCase
{
    /**
     * @var PublisherInterface&MockObject
     */
    private PublisherInterface $publisherMock;

    /**
     * @var SyncPublisher
     */
    private SyncPublisher $syncPublisher;

    protected function setUp(): void
    {
        $this->publisherMock = $this->createMock(PublisherInterface::class);
        $this->syncPublisher = new SyncPublisher($this->publisherMock);
    }

    public function testPublishCallsPublisherWithCorrectTopicAndMessage(): void
    {
        $message = new SyncMessage();
        $message->setEntityType('customer');
        $message->setPage(1);
        $message->setPageSize(50);
        $message->setBatchId('batch-abc');

        $this->publisherMock->expects($this->once())
            ->method('publish')
            ->with(
                'mageclone.sync.entity',
                $message
            );

        $this->syncPublisher->publish($message);
    }

    public function testPublishSendsMessageWithAllFields(): void
    {
        $message = new SyncMessage();
        $message->setEntityType('order');
        $message->setPage(3);
        $message->setPageSize(100);
        $message->setBatchId('batch-xyz');
        $message->setUpdatedSince('2025-01-01 00:00:00');

        $this->publisherMock->expects($this->once())
            ->method('publish')
            ->with(
                'mageclone.sync.entity',
                $this->callback(function (SyncMessage $msg): bool {
                    return $msg->getEntityType() === 'order'
                        && $msg->getPage() === 3
                        && $msg->getPageSize() === 100
                        && $msg->getBatchId() === 'batch-xyz'
                        && $msg->getUpdatedSince() === '2025-01-01 00:00:00';
                })
            );

        $this->syncPublisher->publish($message);
    }
}
