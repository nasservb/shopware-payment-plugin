<?php

namespace Payever\PayeverPayments\tests\unit\Service\Management;

use Payever\ExternalIntegration\Inventory\Http\RequestEntity\InventoryChangedRequestEntity;
use Payever\ExternalIntegration\Inventory\Http\RequestEntity\InventoryCreateRequestEntity;
use Payever\ExternalIntegration\Products\Http\RequestEntity\ProductRemovedRequestEntity;
use Payever\ExternalIntegration\Products\Http\RequestEntity\ProductRequestEntity;
use Payever\ExternalIntegration\ThirdParty\Action\BidirectionalActionProcessor;
use Payever\ExternalIntegration\ThirdParty\Enum\DirectionEnum;
use Payever\PayeverPayments\Service\Helper\ConfigHelper;
use Payever\PayeverPayments\Service\Management\SubscriptionManager;
use Payever\PayeverPayments\Service\Management\SynchronizationManager;
use Payever\PayeverPayments\Service\Management\SynchronizationQueueManager;
use Payever\PayeverPayments\Service\PayeverApi\ProcessorFactory;
use Payever\PayeverPayments\Service\PayeverRegistry;
use Payever\PayeverPayments\Service\Transformer\InventoryTransformer;
use Payever\PayeverPayments\Service\Transformer\ProductTransformer;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;

class SynchronizationManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|ProcessorFactory
     */
    private $processorFactory;

    /**
     * @var MockObject|SynchronizationQueueManager
     */
    private $synchronizationQueueManager;

    /**
     * @var MockObject|SubscriptionManager
     */
    private $subscriptionManager;

    /**
     * @var MockObject|ProductTransformer
     */
    private $productTransformer;

    /**
     * @var MockObject|InventoryTransformer
     */
    private $inventoryTransformer;

    /**
     * @var MockObject|ConfigHelper
     */
    private $configHelper;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var SynchronizationManager
     */
    private $manager;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->processorFactory = $this->getMockBuilder(ProcessorFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->synchronizationQueueManager = $this->getMockBuilder(SynchronizationQueueManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->subscriptionManager = $this->getMockBuilder(SubscriptionManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->productTransformer = $this->getMockBuilder(ProductTransformer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->inventoryTransformer = $this->getMockBuilder(InventoryTransformer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configHelper = $this->getMockBuilder(ConfigHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->manager = new SynchronizationManager(
            $this->processorFactory,
            $this->synchronizationQueueManager,
            $this->subscriptionManager,
            $this->productTransformer,
            $this->inventoryTransformer,
            $this->configHelper,
            $this->logger
        );
        PayeverRegistry::set(PayeverRegistry::LAST_INWARD_PROCESSED_PRODUCT, null);
    }

    public function testHandleProductSave()
    {
        $this->productTransformer->expects($this->once())
            ->method('transformFromShopwareIntoPayever')
            ->willReturn(
                $requestEntity = $this->getMockBuilder(ProductRequestEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $requestEntity->expects($this->once())
            ->method('toString')
            ->willReturn(\json_encode(['some' => 'data']));
        $this->manager->handleProductSave(
            $this->getMockBuilder(ProductEntity::class)
                ->disableOriginalConstructor()
                ->getMock(),
            true
        );
    }

    public function testHandleProductDelete()
    {
        $this->productTransformer->expects($this->once())
            ->method('transformRemovedShopwareIntoPayever')
            ->willReturn(
                $requestEntity = $this->getMockBuilder(ProductRemovedRequestEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $requestEntity->expects($this->once())
            ->method('toString')
            ->willReturn(\json_encode(['some' => 'data']));
        $this->manager->handleProductDelete(
            $this->getMockBuilder(ProductEntity::class)
                ->disableOriginalConstructor()
                ->getMock()
        );
    }

    public function testHandleInventory()
    {
        $this->inventoryTransformer->expects($this->once())
            ->method('transformFromShopwareToPayever')
            ->willReturn(
                $requestEntity = $this->getMockBuilder(InventoryChangedRequestEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $requestEntity->expects($this->once())
            ->method('toString')
            ->willReturn(\json_encode(['some' => 'data']));
        $this->manager->handleInventory(
            $this->getMockBuilder(ProductEntity::class)
                ->disableOriginalConstructor()
                ->getMock(),
            1
        );
    }

    public function testHandleInventoryCaseNoDelta()
    {
        $this->inventoryTransformer->expects($this->once())
            ->method('transformFromCreatedShopwareToPayever')
            ->willReturn(
                $requestEntity = $this->getMockBuilder(InventoryCreateRequestEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $requestEntity->expects($this->once())
            ->method('toString')
            ->willReturn(\json_encode(['some' => 'data']));
        $this->manager->handleInventory(
            $this->getMockBuilder(ProductEntity::class)
                ->disableOriginalConstructor()
                ->getMock()
        );
    }

    public function testHandleAction()
    {
        $this->configHelper->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        $this->configHelper->expects($this->once())
            ->method('isCronMode')
            ->willReturn(false);
        $this->processorFactory->expects($this->once())
            ->method('getBidirectionalSyncActionProcessor')
            ->willReturn(
                $bidirectionalActionProcessor = $this->getMockBuilder(BidirectionalActionProcessor::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $bidirectionalActionProcessor->expects($this->once())
            ->method('processInwardAction');
        $this->manager->handleAction(
            'some-action',
            DirectionEnum::INWARD,
            \json_encode(['some' => 'data'])
        );
    }

    public function testHandleActionCaseEnqueue()
    {
        $this->configHelper->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        $this->configHelper->expects($this->once())
            ->method('isCronMode')
            ->willReturn(true);
        $this->synchronizationQueueManager->expects($this->once())
            ->method('enqueueAction');
        $this->manager->handleAction(
            'some-action',
            DirectionEnum::INWARD,
            \json_encode(['some' => 'data'])
        );
    }

    public function testHandleActionCaseEnqueueCaseException()
    {
        $this->configHelper->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        $this->configHelper->expects($this->once())
            ->method('isCronMode')
            ->willReturn(true);
        $this->synchronizationQueueManager->expects($this->once())
            ->method('enqueueAction')
            ->willThrowException(new \Exception());
        $this->manager->handleAction(
            'some-action',
            DirectionEnum::INWARD,
            \json_encode(['some' => 'data'])
        );
    }

    public function testHandleActionCaseOutward()
    {
        $this->configHelper->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        $this->configHelper->expects($this->once())
            ->method('isProductsOutwardSyncEnabled')
            ->willReturn(true);
        $this->configHelper->expects($this->once())
            ->method('isCronMode')
            ->willReturn(false);
        $this->processorFactory->expects($this->once())
            ->method('getBidirectionalSyncActionProcessor')
            ->willReturn(
                $bidirectionalActionProcessor = $this->getMockBuilder(BidirectionalActionProcessor::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $bidirectionalActionProcessor->expects($this->once())
            ->method('processOutwardAction');
        $this->manager->handleAction(
            'some-action',
            DirectionEnum::OUTWARD,
            \json_encode(['some' => 'data'])
        );
    }

    public function testHandleActionCaseException()
    {
        $this->configHelper->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        $this->configHelper->expects($this->once())
            ->method('isCronMode')
            ->willReturn(false);
        $this->processorFactory->expects($this->once())
            ->method('getBidirectionalSyncActionProcessor')
            ->willReturn(
                $bidirectionalActionProcessor = $this->getMockBuilder(BidirectionalActionProcessor::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $bidirectionalActionProcessor->expects($this->once())
            ->method('processInwardAction')
            ->willThrowException(new \Exception());
        $this->manager->handleAction(
            'some-action',
            DirectionEnum::INWARD,
            \json_encode(['some' => 'data'])
        );
    }
}
