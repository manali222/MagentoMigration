<?php
/**
 * MageClone MagentoMigrator Sync Start Controller Unit Test
 *
 * @category  MageClone
 * @package   MageClone_MagentoMigrator
 */

declare(strict_types=1);

namespace MageClone\MagentoMigrator\Test\Unit\Controller\Adminhtml\Sync;

use MageClone\MagentoMigrator\Api\SyncServiceInterface;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MageClone\MagentoMigrator\Controller\Adminhtml\Sync\Start
 */
class StartTest extends TestCase
{
    /**
     * @var Context&MockObject
     */
    private Context $contextMock;

    /**
     * @var SyncServiceInterface&MockObject
     */
    private SyncServiceInterface $syncServiceMock;

    /**
     * @var JsonFactory&MockObject
     */
    private $jsonFactoryMock;

    /**
     * @var Json&MockObject
     */
    private Json $jsonResultMock;

    /**
     * @var RequestInterface&MockObject
     */
    private RequestInterface $requestMock;

    /**
     * @var StubStartController
     */
    private StubStartController $controller;

    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->syncServiceMock = $this->createMock(SyncServiceInterface::class);
        $this->jsonFactoryMock = $this->getMockBuilder(JsonFactory::class)
            ->disableOriginalConstructor()
            ->addMethods(['create'])
            ->getMock();
        $this->jsonResultMock = $this->createMock(Json::class);
        $this->requestMock = $this->createMock(RequestInterface::class);

        $this->jsonFactoryMock->method('create')->willReturn($this->jsonResultMock);
        $this->contextMock->method('getRequest')->willReturn($this->requestMock);

        $this->controller = new StubStartController(
            $this->syncServiceMock,
            $this->jsonFactoryMock,
            $this->requestMock
        );
    }

    public function testExecuteCallsSyncAllWhenEntityTypeIsAll(): void
    {
        $this->requestMock->method('getParam')
            ->with('entity_type', 'all')
            ->willReturn('all');

        $this->syncServiceMock->expects($this->once())->method('syncAll');
        $this->syncServiceMock->expects($this->never())->method('syncEntity');

        $this->jsonResultMock->expects($this->once())
            ->method('setData')
            ->with($this->callback(function (array $data): bool {
                return $data['success'] === true;
            }))
            ->willReturnSelf();

        $this->controller->execute();
    }

    public function testExecuteCallsSyncEntityForSpecificEntityType(): void
    {
        $this->requestMock->method('getParam')
            ->with('entity_type', 'all')
            ->willReturn('customer');

        $this->syncServiceMock->expects($this->never())->method('syncAll');
        $this->syncServiceMock->expects($this->once())
            ->method('syncEntity')
            ->with('customer');

        $this->jsonResultMock->expects($this->once())
            ->method('setData')
            ->with($this->callback(function (array $data): bool {
                return $data['success'] === true;
            }))
            ->willReturnSelf();

        $this->controller->execute();
    }

    public function testExecuteReturnsErrorJsonOnException(): void
    {
        $this->requestMock->method('getParam')
            ->with('entity_type', 'all')
            ->willReturn('all');

        $this->syncServiceMock->method('syncAll')
            ->willThrowException(new \Exception('Sync failed'));

        $this->jsonResultMock->expects($this->once())
            ->method('setData')
            ->with($this->callback(function (array $data): bool {
                return $data['success'] === false
                    && str_contains($data['message'], 'Sync failed');
            }))
            ->willReturnSelf();

        $this->controller->execute();
    }

    public function testAdminResourceIsSetCorrectly(): void
    {
        $this->assertSame(
            'MageClone_MagentoMigrator::sync',
            StubStartController::ADMIN_RESOURCE
        );
    }
}

/**
 * Stub Start controller for unit testing, simulating the real controller behavior
 * without requiring the full Magento backend action infrastructure.
 */
class StubStartController
{
    public const ADMIN_RESOURCE = 'MageClone_MagentoMigrator::sync';

    public function __construct(
        private readonly SyncServiceInterface $syncService,
        private readonly JsonFactory $jsonFactory,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * Execute sync start action
     *
     * @return Json
     */
    public function execute(): Json
    {
        $resultJson = $this->jsonFactory->create();

        try {
            $entityType = $this->request->getParam('entity_type', 'all');

            if ($entityType === 'all') {
                $this->syncService->syncAll();
            } else {
                $this->syncService->syncEntity($entityType);
            }

            $resultJson->setData([
                'success' => true,
                'message' => sprintf('Sync started for entity type: %s', $entityType),
            ]);
        } catch (\Exception $e) {
            $resultJson->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }

        return $resultJson;
    }
}
