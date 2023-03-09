<?php

namespace Payever\PayeverPayments\tests\unit\EventListener;

use Payever\PayeverPayments\EventListener\ProductDeleteEventListener;
use Payever\PayeverPayments\Service\Management\SynchronizationManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;

class ProductDeleteEventListenerTest extends \PHPUnit\Framework\TestCase
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
     * @var ProductDeleteEventListener
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
        $this->listener = new ProductDeleteEventListener(
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
        $this->assertNotEmpty(ProductDeleteEventListener::getSubscribedEvents());
    }

    public function testPreValidateCaseRemoveProduct()
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
                $writeCommand = $this->getMockBuilder(DeleteCommand::class)
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

    public function testOnProductDelete()
    {
        $this->synchronizationManager->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        /** @var MockObject|EntityDeletedEvent $event */
        $event = $this->getMockBuilder(EntityDeletedEvent::class)
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
        $this->listener->setProductsToRemove([
            $id => $this->getMockBuilder(ProductEntity::class)
                ->disableOriginalConstructor()
                ->getMock()
        ]);
        $this->listener->onProductDelete($event);
    }

    public function testOnProductDeleteCaseDisabledSync()
    {
        /** @var MockObject|EntityDeletedEvent $event */
        $event = $this->getMockBuilder(EntityDeletedEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->synchronizationManager->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(false);
        $this->listener->onProductDelete($event);
    }

    public function testOnProductDeleteCaseVariant()
    {
        $this->synchronizationManager->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        /** @var MockObject|EntityDeletedEvent $event */
        $event = $this->getMockBuilder(EntityDeletedEvent::class)
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
        $this->listener->setProductsToRemove([
            $id => $product = $this->getMockBuilder(ProductEntity::class)
                ->disableOriginalConstructor()
                ->getMock()
        ]);
        $product->expects($this->once())
            ->method('getParentId')
            ->willReturn('some-parent-id');
        if (method_exists($this->context, 'disableCache')) {
            $this->context->expects($this->once())
                ->method('disableCache')
                ->willReturn(
                    $this->getMockBuilder(ProductEntity::class)
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
                    $this->getMockBuilder(ProductEntity::class)
                        ->disableOriginalConstructor()
                        ->getMock()
                );
        }
        $this->listener->onProductDelete($event);
    }
}
