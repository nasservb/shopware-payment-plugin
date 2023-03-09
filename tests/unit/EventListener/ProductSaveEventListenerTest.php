<?php

namespace Payever\PayeverPayments\tests\unit\EventListener;

use Payever\PayeverPayments\EventListener\ProductSaveEventListener;
use Payever\PayeverPayments\Service\Management\SynchronizationManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;

class ProductSaveEventListenerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var MockObject|SynchronizationManager
     */
    private $synchronizationManager;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var MockObject|Context
     */
    private $context;

    /**
     * @var ProductSaveEventListener
     */
    private $listener;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->productRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->synchronizationManager = $this->getMockBuilder(SynchronizationManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->listener = new ProductSaveEventListener(
            $this->productRepository,
            $this->synchronizationManager,
            $this->logger
        );
        $this->context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->listener->setContext($this->context);
    }

    public function testGetSubscribedEvents()
    {
        $this->assertNotEmpty(ProductSaveEventListener::getSubscribedEvents());
    }

    public function testPreValidate()
    {
        $this->synchronizationManager->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        /** @var MockObject|PreWriteValidationEvent $event */
        $event = $this->getMockBuilder(PreWriteValidationEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getCommands')
            ->willReturn([
                $writeCommand = $this->getMockBuilder(UpdateCommand::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $writeCommand->expects($this->once())
            ->method('getDefinition')
            ->willReturn(new ProductDefinition());
        $writeCommand->expects($this->once())
            ->method('getEntityExistence')
            ->willReturn(
                $entityExistence = $this->getMockBuilder(EntityExistence::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entityExistence->expects($this->once())
            ->method('getPrimaryKey')
            ->willReturn(['id' => 'some-id']);
        $writeCommand->expects($this->once())
            ->method('getPayload')
            ->willReturn(['stock' => 1]);
        $this->productRepository->expects($this->once())
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
            ->method('first')
            ->willReturn(
                $this->getMockBuilder(ProductEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->listener->preValidate($event);
    }

    public function testPreValidateCaseDisabledSync()
    {
        /** @var MockObject|PreWriteValidationEvent $event */
        $event = $this->getMockBuilder(PreWriteValidationEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->synchronizationManager->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(false);
        $this->listener->preValidate($event);
    }

    public function testOnProductSave()
    {
        $this->synchronizationManager->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        /** @var MockObject|EntityWrittenEvent $event */
        $event = $this->getMockBuilder(EntityWrittenEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->any())
            ->method('getWriteResults')
            ->willReturn([
                $writeResult = $this->getMockBuilder(EntityWriteResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $writeResult->expects($this->any())
            ->method('getOperation')
            ->willReturn(EntityWriteResult::OPERATION_UPDATE);
        $writeResult->expects($this->any())
            ->method('getExistence')
            ->willReturn(
                $existence = $this->getMockBuilder(EntityExistence::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $existence->expects($this->once())
            ->method('getPrimaryKey')
            ->willReturn([
                'id' => $id = 'some-id',
            ]);
        $this->listener->setAffectedProductStocks([$id => 1]);
        if (method_exists($this->context, 'disableCache')) {
            $this->context->expects($this->once())
                ->method('disableCache')
                ->willReturn(
                    $product = $this->getMockBuilder(ProductEntity::class)
                        ->disableOriginalConstructor()
                        ->getMock()
                );
        } else {
            $this->productRepository->expects($this->once())
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
                ->method('first')
                ->willReturn(
                    $product = $this->getMockBuilder(ProductEntity::class)
                        ->disableOriginalConstructor()
                        ->getMock()
                );
        }
        $product->expects($this->once())
            ->method('getStock')
            ->willReturn(2);
        $this->listener->onProductSave($event);
    }

    public function testOnProductSaveCaseVariant()
    {
        $this->synchronizationManager->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        /** @var MockObject|EntityWrittenEvent $event */
        $event = $this->getMockBuilder(EntityWrittenEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->any())
            ->method('getWriteResults')
            ->willReturn([
                $writeResult = $this->getMockBuilder(EntityWriteResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $writeResult->expects($this->any())
            ->method('getOperation')
            ->willReturn(EntityWriteResult::OPERATION_UPDATE);
        $writeResult->expects($this->any())
            ->method('getExistence')
            ->willReturn(
                $existence = $this->getMockBuilder(EntityExistence::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $existence->expects($this->once())
            ->method('getPrimaryKey')
            ->willReturn([
                'id' => $id = 'some-id',
            ]);
        $this->listener->setAffectedProductStocks([$id => 1]);
        $this->productRepository->expects($this->any())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->any())
            ->method('getEntities')
            ->willReturn(
                $entityCollection = $this->getMockBuilder(EntityCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entityCollection->expects($this->any())
            ->method('first')
            ->willReturn(
                $product = $this->getMockBuilder(ProductEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $product->expects($this->once())
            ->method('getParentId')
            ->willReturn('some-parent-id');
        $this->listener->onProductSave($event);
    }

    public function testOnProductSaveCaseUnknownOperation()
    {
        $this->synchronizationManager->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        /** @var MockObject|EntityWrittenEvent $event */
        $event = $this->getMockBuilder(EntityWrittenEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getWriteResults')
            ->willReturn([
                $writeResult = $this->getMockBuilder(EntityWriteResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $writeResult->expects($this->once())
            ->method('getOperation')
            ->willReturn('unknown_operation');
        $this->listener->onProductSave($event);
    }
}
