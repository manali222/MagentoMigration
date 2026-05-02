<?php
/**
 * MageClone MagentoMigrator Sync Status Controller
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Controller\Adminhtml\Sync;

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
        private readonly JsonFactory $resultJsonFactory
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
            $data = [];

            foreach ($statuses as $status) {
                $data[] = [
                    'entity_type' => $status->getEntityType(),
                    'source_count' => $status->getSourceCount(),
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
}
