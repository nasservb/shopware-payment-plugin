<?php

namespace Payever\PayeverPayments\tests\unit\Service\Management;

use Payever\PayeverPayments\Service\Management\SynchronizationQueueManager;
use Payever\PayeverPayments\SynchronizationQueue\SynchronizationQueueEntity;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class SynchronizationQueueManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $synchronizationQueueRepository;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var MockObject|Context
     */
    protected $context;

    /**
     * @var SynchronizationQueueManager
     */
    private $manager;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->synchronizationQueueRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->manager = new SynchronizationQueueManager(
            $this->synchronizationQueueRepository,
            $this->logger
        );
        $this->manager->setContext($this->context);
    }

    public function testEnqueueAction()
    {
        $this->synchronizationQueueRepository->expects($this->once())
            ->method('upsert')
            ->willThrowException(new \Exception());
        $this->manager->enqueueAction('create-product', 'outward', \json_encode(['some' => 'data']));
    }

    public function testGetEntities()
    {
        $this->synchronizationQueueRepository->expects($this->once())
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
            ->willReturn([
                $this->getMockBuilder(SynchronizationQueueEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $this->assertNotEmpty($this->manager->getEntities());
    }

    public function testUpdateAttempt()
    {
        /** @var MockObject|SynchronizationQueueEntity $entity */
        $entity = $this->getMockBuilder(SynchronizationQueueEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->synchronizationQueueRepository->expects($this->once())
            ->method('update');
        $this->manager->updateAttempt($entity);
    }

    public function testRemove()
    {
        /** @var MockObject|SynchronizationQueueEntity $entity */
        $entity = $this->getMockBuilder(SynchronizationQueueEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->synchronizationQueueRepository->expects($this->once())
            ->method('delete');
        $this->manager->remove($entity);
    }

    public function testEmptyQueue()
    {
        $this->synchronizationQueueRepository->expects($this->once())
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
            ->willReturn([
                $this->getMockBuilder(SynchronizationQueueEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $this->synchronizationQueueRepository->expects($this->once())
            ->method('delete');
        $this->manager->emptyQueue();
    }
}
