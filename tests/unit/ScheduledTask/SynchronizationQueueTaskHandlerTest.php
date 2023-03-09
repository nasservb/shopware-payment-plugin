<?php

namespace Payever\PayeverPayments\tests\unit\ScheduledTask;

use Payever\PayeverPayments\ScheduledTask\SynchronizationQueueTaskHandler;
use Payever\PayeverPayments\Service\Helper\ConfigHelper;
use Payever\PayeverPayments\Service\Management\SynchronizationManager;
use Payever\PayeverPayments\Service\Management\SynchronizationQueueManager;
use Payever\PayeverPayments\SynchronizationQueue\SynchronizationQueueEntity;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class SynchronizationQueueTaskHandlerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|SynchronizationQueueManager
     */
    private $synchronizationQueueManager;

    /**
     * @var MockObject|SynchronizationManager
     */
    private $synchronizationManager;

    /**
     * @var MockObject|ConfigHelper
     */
    private $configHelper;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var SynchronizationQueueTaskHandler
     */
    private $handler;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        /** @var MockObject|EntityRepositoryInterface $scheduledTaskRepository */
        $scheduledTaskRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->synchronizationQueueManager = $this->getMockBuilder(SynchronizationQueueManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->synchronizationManager = $this->getMockBuilder(SynchronizationManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configHelper = $this->getMockBuilder(ConfigHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->handler = new SynchronizationQueueTaskHandler(
            $scheduledTaskRepository,
            $this->synchronizationQueueManager,
            $this->synchronizationManager,
            $this->configHelper,
            $this->logger
        );
    }

    public function testGetHandledMessages()
    {
        $this->assertNotEmpty(SynchronizationQueueTaskHandler::getHandledMessages());
    }

    public function testRun()
    {
        $this->configHelper->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        $this->synchronizationQueueManager->expects($this->once())
            ->method('getEntities')
            ->willReturn([
                $item = $this->getMockBuilder(SynchronizationQueueEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $item->expects($this->once())
            ->method('getAttempt')
            ->willReturn(0);
        $item->expects($this->once())
            ->method('getAction')
            ->willReturn('some-action');
        $item->expects($this->once())
            ->method('getDirection')
            ->willReturn('some-direction');
        $item->expects($this->once())
            ->method('getPayload')
            ->willReturn(\json_encode(['some' => 'data']));
        $this->handler->run();
    }

    public function testRunCaseDelay()
    {
        $this->configHelper->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        $this->synchronizationQueueManager->expects($this->once())
            ->method('getEntities')
            ->willReturn([
                $item = $this->getMockBuilder(SynchronizationQueueEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $item->expects($this->once())
            ->method('getAttempt')
            ->willReturn(0);
        $item->expects($this->once())
            ->method('getAction')
            ->willReturn('some-action');
        $item->expects($this->once())
            ->method('getDirection')
            ->willReturn('some-direction');
        $item->expects($this->once())
            ->method('getPayload')
            ->willReturn(\json_encode(['some' => 'data']));
        $this->synchronizationManager->expects($this->once())
            ->method('handleAction')
            ->willThrowException(new \Exception());
        $this->handler->run();
    }

    public function testRunCaseRemoveQueueItem()
    {
        $this->configHelper->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        $this->synchronizationQueueManager->expects($this->once())
            ->method('getEntities')
            ->willReturn([
                $item = $this->getMockBuilder(SynchronizationQueueEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $item->expects($this->once())
            ->method('getAttempt')
            ->willReturn(2);
        $item->expects($this->once())
            ->method('getAction')
            ->willReturn('some-action');
        $item->expects($this->once())
            ->method('getDirection')
            ->willReturn('some-direction');
        $item->expects($this->once())
            ->method('getPayload')
            ->willReturn(\json_encode(['some' => 'data']));
        $this->synchronizationManager->expects($this->once())
            ->method('handleAction')
            ->willThrowException(new \Exception());
        $this->handler->run();
    }
}
