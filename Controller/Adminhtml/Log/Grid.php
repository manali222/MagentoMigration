<?php
/**
 * MageClone MagentoMigrator Sync Log Grid AJAX Controller
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Controller\Adminhtml\Log;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\View\LayoutFactory;

/**
 * Class Grid
 *
 * AJAX grid refresh controller for the sync log listing.
 */
class Grid extends Action
{
    /**
     * Authorization level
     */
    public const ADMIN_RESOURCE = 'MageClone_MagentoMigrator::sync';

    /**
     * @param Context $context
     * @param RawFactory $resultRawFactory
     * @param LayoutFactory $layoutFactory
     */
    public function __construct(
        Context $context,
        private readonly RawFactory $resultRawFactory,
        private readonly LayoutFactory $layoutFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Execute AJAX grid refresh
     *
     * @return Raw
     */
    public function execute(): Raw
    {
        $layout = $this->layoutFactory->create();
        $resultRaw = $this->resultRawFactory->create();

        $resultRaw->setContents(
            $layout->createBlock(
                \Magento\Backend\Block\Widget\Grid::class
            )->toHtml()
        );

        return $resultRaw;
    }
}
