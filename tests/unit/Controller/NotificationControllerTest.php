<?php

namespace Payever\PayeverPayments\tests\unit\Controller;

use Payever\ExternalIntegration\Payments\Notification\NotificationResult;
use Payever\PayeverPayments\Controller\NotificationController;
use Payever\PayeverPayments\Service\Payment\Notification\NotificationRequestProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;

class NotificationControllerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|ContainerInterface
     */
    private $container;

    /** @var MockObject|NotificationRequestProcessor */
    private $notificationRequestProcessor;

    /** @var NotificationController */
    private $controller;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->notificationRequestProcessor = $this->getMockBuilder(NotificationRequestProcessor::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->controller = new NotificationController($this->notificationRequestProcessor);
        $this->controller->setContainer($this->container);
    }

    public function testExecute()
    {
        $this->notificationRequestProcessor->expects($this->once())
            ->method('processNotification')
            ->willReturn(
                $notificationResult = $this->getMockBuilder(NotificationResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $notificationResult->expects($this->once())
            ->method('isFailed')
            ->willReturn(false);

        $this->assertNotEmpty($this->controller->execute());
    }

    public function testExecuteCaseException()
    {
        $this->notificationRequestProcessor->expects($this->once())
            ->method('processNotification')
            ->willThrowException(new \Exception());
        $this->assertNotEmpty($this->controller->execute());
    }
}
