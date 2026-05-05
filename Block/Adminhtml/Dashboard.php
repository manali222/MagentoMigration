<?php
/**
 * MageClone MagentoMigrator Dashboard Block
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Block\Adminhtml;

use MageClone\MagentoMigrator\Api\Data\SyncStatusInterface;
use MageClone\MagentoMigrator\Api\GraphQlClientInterface;
use MageClone\MagentoMigrator\Api\SyncServiceInterface;
use MageClone\MagentoMigrator\Model\Config;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;

/**
 * Class Dashboard
 *
 * Block class for the migration dashboard template.
 */
class Dashboard extends Template
{
    /**
     * @param Context $context
     * @param SyncServiceInterface $syncService
     * @param GraphQlClientInterface $graphQlClient
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly SyncServiceInterface $syncService,
        private readonly GraphQlClientInterface $graphQlClient,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Test connection to the source Magento instance
     *
     * @return bool
     */
    public function getConnectionStatus(): bool
    {
        try {
            return $this->graphQlClient->testConnection();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get current sync statuses for all entity types
     *
     * @return SyncStatusInterface[]
     */
    public function getSyncStatuses(): array
    {
        try {
            return $this->syncService->getSyncStatus();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get the configured source URL
     *
     * @return string
     */
    public function getSourceUrl(): string
    {
        return $this->config->getSourceUrl() ?? __('Not Configured')->render();
    }

    /**
     * Get the URL for the sync start AJAX endpoint
     *
     * @return string
     */
    public function getSyncStartUrl(): string
    {
        return $this->getUrl('mageclone/sync/start');
    }

    /**
     * Get the URL for the sync status AJAX endpoint
     *
     * @return string
     */
    public function getStatusUrl(): string
    {
        return $this->getUrl('mageclone/sync/status');
    }

    /**
     * Get the URL for the resync failed AJAX endpoint
     *
     * @return string
     */
    public function getResyncUrl(): string
    {
        return $this->getUrl('mageclone/sync/resyncFailed');
    }

    /**
     * Get the URL for the sync logs page
     *
     * @return string
     */
    public function getLogsUrl(): string
    {
        return $this->getUrl('mageclone/log/index');
    }

    /**
     * Get list of available entity types
     *
     * @return string[]
     */
    public function getEntityTypes(): array
    {
        try {
            return $this->syncService->getAvailableEntityTypes();
        } catch (\Exception $e) {
            return [];
        }
    }
}
