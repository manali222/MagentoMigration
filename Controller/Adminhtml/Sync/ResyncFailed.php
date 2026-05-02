<?php
/**
 * MageClone MagentoMigrator Resync Failed Controller
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Controller\Adminhtml\Sync;

use MageClone\MagentoMigrator\Api\Data\SyncStatusInterface;
use MageClone\MagentoMigrator\Api\SyncServiceInterface;
use MageClone\MagentoMigrator\Api\SyncStatusRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Class ResyncFailed
 *
 * AJAX endpoint to retry synchronization of failed records for a given entity type.
 */
class ResyncFailed extends Action
{
    /**
     * Authorization level
     */
    public const ADMIN_RESOURCE = 'MageClone_MagentoMigrator::sync';

    /**
     * @param Context $context
     * @param SyncServiceInterface $syncService
     * @param SyncStatusRepositoryInterface $syncStatusRepository
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Context $context,
        private readonly SyncServiceInterface $syncService,
        private readonly SyncStatusRepositoryInterface $syncStatusRepository,
        private readonly JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Execute resync failed records action
     *
     * @return Json
     */
    public function execute(): Json
    {
        $entityType = $this->getRequest()->getParam('entity_type', 'all');
        $result = $this->resultJsonFactory->create();

        try {
            if ($entityType === 'all') {
                $availableTypes = $this->syncService->getAvailableEntityTypes();
                foreach ($availableTypes as $type) {
                    $this->resetFailedAndResync($type);
                }
            } else {
                $this->resetFailedAndResync((string) $entityType);
            }

            return $result->setData([
                'success' => true,
                'message' => __('Resync of failed records started successfully.')
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Reset failed count and trigger resync for a specific entity type
     *
     * @param string $entityType
     * @return void
     */
    private function resetFailedAndResync(string $entityType): void
    {
        try {
            $syncStatus = $this->syncStatusRepository->getByEntityType($entityType);

            if ($syncStatus->getFailedCount() > 0) {
                $syncStatus->setPendingCount($syncStatus->getFailedCount());
                $syncStatus->setFailedCount(0);
                $syncStatus->setStatus(SyncStatusInterface::STATUS_RUNNING);
                $this->syncStatusRepository->save($syncStatus);

                $this->syncService->syncEntity($entityType);
            }
        } catch (\Exception $e) {
            // Entity type has no status record yet; trigger a full sync instead
            $this->syncService->syncEntity($entityType);
        }
    }
}
