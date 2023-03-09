<?php

namespace Payever\PayeverPayments\tests\unit\Service\Management;

use Payever\ExternalIntegration\Inventory\InventoryApiClient;
use Payever\ExternalIntegration\Products\ProductsApiClient;
use Payever\PayeverPayments\Messenger\ExportProducer;
use Payever\PayeverPayments\Service\Helper\ConfigHelper;
use Payever\PayeverPayments\Service\Management\ExportManager;
use Payever\PayeverPayments\Service\Management\SubscriptionManager;
use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use Payever\PayeverPayments\Service\Transformer\ProductTransformer;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class ExportManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $entityRepository;

    /**
     * @var MockObject|ClientFactory
     */
    private $clientFactory;

    /**
     * @var MockObject|ProductTransformer
     */
    private $productTransformer;

    /**
     * @var MockObject|ExportProducer
     */
    private $exportProducer;

    /**
     * @var MockObject|SubscriptionManager
     */
    private $subscriptionManager;

    /**
     * @var MockObject|ConfigHelper
     */
    private $configHelper;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var ExportManager
     */
    private $manager;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->entityRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->clientFactory = $this->getMockBuilder(ClientFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->productTransformer = $this->getMockBuilder(ProductTransformer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->exportProducer = $this->getMockBuilder(ExportProducer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->subscriptionManager = $this->getMockBuilder(SubscriptionManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configHelper = $this->getMockBuilder(ConfigHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->manager = new ExportManager(
            $this->entityRepository,
            $this->clientFactory,
            $this->productTransformer,
            $this->exportProducer,
            $this->subscriptionManager,
            $this->configHelper,
            $this->logger
        );
    }

    public function testEnqueueExport()
    {
        $this->configHelper->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        $this->configHelper->expects($this->once())
            ->method('isProductsOutwardSyncEnabled')
            ->willReturn(true);
        $this->entityRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('getTotal')
            ->willReturn(10);
        $this->exportProducer->expects($this->exactly(2))
            ->method('produce');
        $this->assertTrue($this->manager->enqueueExport());
        $this->assertEquals(2, $this->manager->getBatchCount());
    }

    public function testEnqueueExportCaseException()
    {
        $this->configHelper->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        $this->configHelper->expects($this->once())
            ->method('isProductsOutwardSyncEnabled')
            ->willReturn(true);
        $this->entityRepository->expects($this->once())
            ->method('search')
            ->willThrowException(new \Exception());
        $this->assertFalse($this->manager->enqueueExport());
    }

    public function testProcessBatch()
    {
        $this->entityRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn(
                $entityCollection = $this->getMockBuilder(EntityCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entityCollection->expects($this->once())
            ->method('getElements')
            ->willReturn([]);
        $this->configHelper->expects($this->once())
            ->method('getProductsSyncExternalId')
            ->willReturn('some-external-id');
        $this->clientFactory->expects($this->once())
            ->method('getProductsApiClient')
            ->willReturn(
                $productsApiClient = $this->getMockBuilder(ProductsApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $productsApiClient->expects($this->once())
            ->method('exportProducts')
            ->willReturn($aggregate = 5);
        $this->clientFactory->expects($this->once())
            ->method('getInventoryApiClient')
            ->willReturn(
                $this->getMockBuilder(InventoryApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->assertEquals($aggregate, $this->manager->processBatch(5, 0));
    }

    public function testProcessBatchCaseException()
    {
        $this->entityRepository->expects($this->once())
            ->method('search')
            ->willThrowException(new \Exception());
        $this->subscriptionManager->expects($this->once())
            ->method('disable');
        $this->manager->processBatch(1, 0);
    }
}
