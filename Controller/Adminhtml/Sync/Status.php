<?php
/**
 * MageClone MagentoMigrator Sync Status Controller
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Controller\Adminhtml\Sync;

use MageClone\MagentoMigrator\Api\GraphQlClientInterface;
use MageClone\MagentoMigrator\Api\SyncServiceInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Class Status
 *
 * AJAX endpoint returning current sync status for all entity types.
 */
class Status extends Action
{
    /**
     * Authorization level
     */
    public const ADMIN_RESOURCE = 'MageClone_MagentoMigrator::sync';

    /**
     * @param Context $context
     * @param SyncServiceInterface $syncService
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        private readonly SyncServiceInterface $syncService,
        private readonly JsonFactory $resultJsonFactory,
        private readonly GraphQlClientInterface $graphQlClient
    ) {
        parent::__construct($context);
    }

    /**
     * Execute status retrieval action
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->resultJsonFactory->create();

        try {
            $statuses = $this->syncService->getSyncStatus();
            $sourceCounts = $this->fetchSourceCounts();
            $data = [];

            foreach ($statuses as $status) {
                $entityType = $status->getEntityType();
                $sourceCount = $sourceCounts[$entityType] ?? $status->getSourceCount();
                $data[] = [
                    'entity_type' => $entityType,
                    'source_count' => $sourceCount,
                    'destination_count' => $status->getDestinationCount(),
                    'synced_count' => $status->getSyncedCount(),
                    'failed_count' => $status->getFailedCount(),
                    'pending_count' => $status->getPendingCount(),
                    'status' => $status->getStatus(),
                    'last_synced_at' => $status->getLastSyncedAt(),
                ];
            }

            return $result->setData($data);
        } catch (\Exception $e) {
            return $result->setData([
                'error' => true,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Fetch live source counts via GraphQL
     *
     * @return array
     */
    private function fetchSourceCounts(): array
    {
        try {
            $data = $this->graphQlClient->query(
                '{ magecloneMigrationMetadata { customer_count product_count category_count order_count cms_page_count cms_block_count store_config_count } }'
            );
            $meta = $data['magecloneMigrationMetadata'] ?? [];
            return [
                'customer' => $meta['customer_count'] ?? 0,
                'product' => $meta['product_count'] ?? 0,
                'category' => $meta['category_count'] ?? 0,
                'order' => $meta['order_count'] ?? 0,
                'cms_page' => $meta['cms_page_count'] ?? 0,
                'cms_block' => $meta['cms_block_count'] ?? 0,
                'store_config' => $meta['store_config_count'] ?? 0,
                'eav_attribute' => 0,
                'custom_table' => 0,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }
}
