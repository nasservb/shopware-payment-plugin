<?php

namespace Payever\PayeverPayments\tests\unit\Controller;

use Payever\PayeverPayments\Controller\CancelPaymentController;
use Payever\PayeverPayments\Service\Payment\TransactionStatusService;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Request;

class CancelPaymentControllerTest extends \PHPUnit\Framework\TestCase
{
    /** @var MockObject|ContainerInterface */
    private $container;

    /** @var MockObject|TransactionStatusService */
    private $transactionStatusService;

    /** @var CancelPaymentController */
    private $controller;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->transactionStatusService = $this->getMockBuilder(TransactionStatusService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->controller = new CancelPaymentController($this->transactionStatusService);
        $this->controller->setContainer($this->container);
    }

    public function testCancel()
    {
        /** @var MockObject|SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $salesChannelContext->expects($this->any())
            ->method('getContext')
            ->willReturn(
                $this->getMockBuilder(Context::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->transactionStatusService->expects($this->once())
            ->method('getOrderTransactionById')
            ->willReturn(
                $this->getMockBuilder(OrderTransactionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->container->expects($this->any())
            ->method('has')
            ->willReturn(true);
        $containerMocks = [
            'translator' => $translator = $this->getMockBuilder(TranslatorInterface::class)
                ->disableOriginalConstructor()
                ->getMock(),
            'session' => $session = $this->getMockBuilder(Session::class)
                ->disableOriginalConstructor()
                ->getMock(),
            'router' => $router = $this->getMockBuilder(RouterInterface::class)
                ->disableOriginalConstructor()
                ->getMock(),
            'request_stack' => $requestStack = $this->getMockBuilder(Request::class)
                                              ->disableOriginalConstructor()
                                              ->getMock(),
        ];




        $this->container->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) use ($containerMocks) {
                return $containerMocks[$key] ?? null;
            });
        $translator->expects($this->once())
            ->method('trans')
            ->willReturn('some-translation');
        $session->expects($this->once())
            ->method('getFlashBag')
            ->willReturn(
                $this->getMockBuilder(FlashBagInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $router->expects($this->once())
            ->method('generate')
            ->willReturn('http://some.domain/path');

        $requestStack->expects($this->any())
                     ->method('getSession')
                     ->willReturn($session);

        $this->controller->cancel('some-transaction-id', $salesChannelContext);
    }
}
