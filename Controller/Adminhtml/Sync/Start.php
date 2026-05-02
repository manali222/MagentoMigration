<?php
/**
 * MageClone MagentoMigrator Start Sync Controller
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
 * Class Start
 *
 * AJAX endpoint to start entity synchronization.
 */
class Start extends Action
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
     * Execute sync start action
     *
     * @return Json
     */
    public function execute(): Json
    {
        $entityType = $this->getRequest()->getParam('entity_type');
        $result = $this->resultJsonFactory->create();

        try {
            if ($entityType === 'all') {
                $this->syncService->syncAll();
            } else {
                $this->syncService->syncEntity((string) $entityType);
            }

            return $result->setData([
                'success' => true,
                'message' => __('Sync started successfully.')
            ]);
        } catch (\Exception $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
}
