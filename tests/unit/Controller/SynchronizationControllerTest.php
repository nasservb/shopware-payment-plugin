<?php

namespace Payever\PayeverPayments\tests\unit\Controller;

use Payever\PayeverPayments\Controller\SynchronizationController;
use Payever\PayeverPayments\Service\Payment\PaymentOptionsService;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Context;

class SynchronizationControllerTest extends \PHPUnit\Framework\TestCase
{
    /** @var MockObject|ContainerInterface */
    private $container;

    /** @var MockObject|PaymentOptionsService */
    private $paymentOptionsHandler;

    /** @var SynchronizationController */
    private $controller;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentOptionsHandler = $this->getMockBuilder(PaymentOptionsService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->controller = new SynchronizationController($this->paymentOptionsHandler);
        $this->controller->setContainer($this->container);
    }

    public function testSynchronization()
    {
        /** @var MockObject|Context $context */
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentOptionsHandler->expects($this->once())
            ->method('synchronizePaymentOptions')
            ->willReturn(['some' => 'data']);
        $this->controller->synchronization($context);
    }

    public function testSynchronizationCaseException()
    {
        /** @var MockObject|Context $context */
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentOptionsHandler->expects($this->once())
            ->method('synchronizePaymentOptions')
            ->willThrowException(new \Exception());
        $this->controller->synchronization($context);
    }
}
