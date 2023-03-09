<?php

namespace Payever\PayeverPayments\tests\unit\Controller;

use Payever\ExternalIntegration\Payments\Action\ActionDeciderInterface;
use Payever\PayeverPayments\Controller\PaymentActionController;
use Payever\PayeverPayments\Service\Payment\PaymentActionService;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Symfony\Component\HttpFoundation\Request;

class PaymentActionControllerTest extends \PHPUnit\Framework\TestCase
{
    /** @var MockObject|ContainerInterface */
    private $container;

    /** @var MockObject|PaymentActionService */
    private $payeverTriggersHandler;

    /** @var MockObject|EntityRepositoryInterface */
    private $transactionRepository;

    /** @var PaymentActionController */
    private $controller;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->payeverTriggersHandler = $this->getMockBuilder(PaymentActionService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->transactionRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->controller = new PaymentActionController($this->payeverTriggersHandler, $this->transactionRepository);
        $this->controller->setContainer($this->container);
    }

    public function testHandleRequestLegacyCaseNoTransactionId()
    {
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|Context $context */
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->assertNotEmpty($this->controller->handleRequestLegacy($request, $context));
    }

    public function testHandleRequestCaseNoTransactionId()
    {
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|Context $context */
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->assertNotEmpty($this->controller->handleRequest($request, $context));
    }

    public function testHandleRequestCaseNoOrderTransaction()
    {
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) {
                if ('action' === $key) {
                    return ActionDeciderInterface::ACTION_CANCEL;
                }
                if ('transaction' === $key) {
                    return 'some-transaction-id';
                }
            });
        /** @var MockObject|Context $context */
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->transactionRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $searchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $searchResult->expects($this->once())
            ->method('first')
            ->willReturn(null);
        $this->assertNotEmpty($this->controller->handleRequest($request, $context));
    }

    public function testHandleRequestCaseCancel()
    {
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|Context $context */
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) {
                if ('action' === $key) {
                    return ActionDeciderInterface::ACTION_CANCEL;
                }
                if ('transaction' === $key) {
                    return 'some-transaction-id';
                }
            });
        $this->transactionRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $searchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $searchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->payeverTriggersHandler->expects($this->once())
            ->method('cancelTransaction');
        $this->controller->handleRequest($request, $context);
    }

    public function testHandleRequestCaseShippingGoods()
    {
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|Context $context */
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) {
                if ('action' === $key) {
                    return ActionDeciderInterface::ACTION_SHIPPING_GOODS;
                }
                if ('transaction' === $key) {
                    return 'some-transaction-id';
                }
                if ('amount' === $key) {
                    return 1.1;
                }
            });
        $this->transactionRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $searchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $searchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $this->getMockBuilder(OrderTransactionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->payeverTriggersHandler->expects($this->once())
            ->method('shippingTransaction');
        $this->controller->handleRequest($request, $context);
    }

    public function testHandleRequestCaseRefund()
    {
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|Context $context */
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) {
                if ('action' === $key) {
                    return ActionDeciderInterface::ACTION_REFUND;
                }
                if ('transaction' === $key) {
                    return 'some-transaction-id';
                }
                if ('amount' === $key) {
                    return 1.1;
                }
            });
        $this->transactionRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $searchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $searchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $this->getMockBuilder(OrderTransactionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->payeverTriggersHandler->expects($this->once())
            ->method('refundTransaction');
        $this->controller->handleRequest($request, $context);
    }

    public function testHandleRequestCaseUnknownAction()
    {
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|Context $context */
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) {
                if ('action' === $key) {
                    return 'unknown_action';
                }
                if ('transaction' === $key) {
                    return 'some-transaction-id';
                }
            });
        $this->transactionRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $searchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $searchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $this->getMockBuilder(OrderTransactionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->controller->handleRequest($request, $context);
    }
}
