<?php
/**
 * MageClone MagentoMigrator Sync Queue Publisher
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Model\Queue;

use Magento\Framework\MessageQueue\PublisherInterface;

/**
 * Class SyncPublisher
 *
 * Publishes sync messages to the message queue for asynchronous processing.
 */
class SyncPublisher
{
    private const TOPIC_NAME = 'mageclone.sync.entity';

    /**
     * @param PublisherInterface $publisher
     */
    public function __construct(
        private readonly PublisherInterface $publisher
    ) {
    }

    /**
     * Publish a sync message to the queue
     *
     * @param SyncMessage $message
     * @return void
     */
    public function publish(SyncMessage $message): void
    {
        $this->publisher->publish(self::TOPIC_NAME, $message);
    }
}
