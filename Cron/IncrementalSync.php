<?php
/**
 * MageClone MagentoMigrator Incremental Sync Cron
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Cron;

use MageClone\MagentoMigrator\Api\SyncServiceInterface;
use MageClone\MagentoMigrator\Model\Config;
use Psr\Log\LoggerInterface;

/**
 * Cron job for running incremental entity synchronization.
 */
class IncrementalSync
{
    public function __construct(
        private readonly SyncServiceInterface $syncService,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Execute incremental sync cron job
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isIncrementalEnabled()) {
            return;
        }

        try {
            $this->syncService->syncAll();
        } catch (\Exception $e) {
            $this->logger->error('MageClone incremental sync failed: ' . $e->getMessage());
        }
    }
}
