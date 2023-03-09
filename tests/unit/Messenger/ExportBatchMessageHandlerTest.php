<?php

namespace Payever\PayeverPayments\tests\unit\Messenger;

use Payever\PayeverPayments\Messenger\ExportBatchMessage;
use Payever\PayeverPayments\Messenger\ExportBatchMessageHandler;
use Payever\PayeverPayments\Service\Management\ExportManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class ExportBatchMessageHandlerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|ExportManager
     */
    private $exportManager;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var ExportBatchMessageHandler
     */
    private $handler;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->exportManager = $this->getMockBuilder(ExportManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->handler = new ExportBatchMessageHandler($this->exportManager, $this->logger);
    }

    public function testGetHandledMessages()
    {
        $this->assertNotEmpty(ExportBatchMessageHandler::getHandledMessages());
    }

    public function testInvoke()
    {
        $message = $this->getMockBuilder(ExportBatchMessage::class)
            ->disableOriginalConstructor()
            ->getMock();
        $message->expects($this->once())
            ->method('getLimit')
            ->willReturn(5);
        $message->expects($this->once())
            ->method('getOffset')
            ->willReturn(10);
        $this->exportManager->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        $this->exportManager->expects($this->once())
            ->method('isProductsOutwardSyncEnabled')
            ->willReturn(true);
        $this->exportManager->expects($this->once())
            ->method('processBatch');
        $this->handler->__invoke($message);
    }

    public function testInvokeCaseException()
    {
        $message = $this->getMockBuilder(ExportBatchMessage::class)
            ->disableOriginalConstructor()
            ->getMock();
        $message->expects($this->once())
            ->method('getLimit')
            ->willReturn(5);
        $message->expects($this->once())
            ->method('getOffset')
            ->willReturn(10);
        $this->exportManager->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        $this->exportManager->expects($this->once())
            ->method('isProductsOutwardSyncEnabled')
            ->willReturn(true);
        $this->exportManager->expects($this->once())
            ->method('processBatch')
            ->willThrowException(new \Exception());
        $this->handler->__invoke($message);
    }

    public function testInvokeCaseDisabledSync()
    {
        $message = $this->getMockBuilder(ExportBatchMessage::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->exportManager->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(false);
        $this->handler->__invoke($message);
    }

    public function testInvokeCaseUnsupportedMessage()
    {
        $this->logger->expects($this->once())
            ->method('warning');
        $this->handler->__invoke(new \stdClass());
    }
}
