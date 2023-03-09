<?php

namespace Payever\PayeverPayments\tests\unit\Service\Payment\FinanceExpress;

use Payever\ExternalIntegration\Core\Lock\LockInterface;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\PayeverPayments\Service\PayeverPayment;
use Payever\PayeverPayments\Service\Payment\FinanceExpress\CustomerHelper;
use Payever\PayeverPayments\Service\Payment\FinanceExpress\OrderHelper;
use Payever\PayeverPayments\Service\Payment\FinanceExpress\SuccessHandler;
use Payever\PayeverPayments\Service\Payment\TransactionStatusService;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SuccessHandlerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|PayeverPayment
     */
    protected $paymentHandler;

    /**
     * @var MockObject|TransactionStatusService
     */
    protected $transactionStatusService;

    /**
     * @var MockObject|CustomerHelper
     */
    protected $customerHelper;

    /**
     * @var MockObject|OrderHelper
     */
    protected $orderHelper;

    /**
     * @var MockObject|LockInterface
     */
    protected $locker;

    /**
     * @var MockObject|LoggerInterface
     */
    protected $logger;

    /**
     * @var SuccessHandler
     */
    private $handler;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->paymentHandler = $this->getMockBuilder(PayeverPayment::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->transactionStatusService = $this->getMockBuilder(TransactionStatusService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->customerHelper = $this->getMockBuilder(CustomerHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderHelper = $this->getMockBuilder(OrderHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->locker = $this->getMockBuilder(LockInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->handler = new SuccessHandler(
            $this->paymentHandler,
            $this->transactionStatusService,
            $this->customerHelper,
            $this->orderHelper,
            $this->locker,
            $this->logger
        );
    }

    public function testHandle()
    {
        /** @var MockObject|SalesChannelContext $context */
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentHandler->expects($this->once())
            ->method('retrieveRequest')
            ->willReturn(
                $paymentResult = $this->getMockBuilder(RetrievePaymentResultEntity::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getStatus'])
                    ->getMock()
            );
        $paymentResult->expects($this->once())
            ->method('getStatus')
            ->willReturn('STATUS_IN_PROCESS');
        $this->transactionStatusService->expects($this->once())
            ->method('isSuccessfulPaymentStatus')
            ->willReturn(true);
        $this->customerHelper->expects($this->once())
            ->method('getCustomer')
            ->willReturn(
                $this->getMockBuilder(CustomerEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->orderHelper->expects($this->once())
            ->method('getOrder')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->transactionStatusService->expects($this->once())
            ->method('persistTransactionStatus');
        $order->expects($this->once())
            ->method('getId')
            ->willReturn('some-order-uuid');
        $this->assertNotEmpty($this->handler->handle($context, 'some-payment-uuid'));
    }

    public function testHandleCaseNoPaymentResult()
    {
        /** @var MockObject|SalesChannelContext $context */
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->transactionStatusService->expects($this->never())
            ->method('persistTransactionStatus');
        $this->assertEmpty($this->handler->handle($context, 'some-payment-uuuid'));
    }

    public function testHandleCaseUnsuccessfulPaymentStatus()
    {
        /** @var MockObject|SalesChannelContext $context */
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentHandler->expects($this->once())
            ->method('retrieveRequest')
            ->willReturn(
                $paymentResult = $this->getMockBuilder(RetrievePaymentResultEntity::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getStatus'])
                    ->getMock()
            );
        $paymentResult->expects($this->once())
            ->method('getStatus')
            ->willReturn('STATUS_FAILED');
        $this->transactionStatusService->expects($this->once())
            ->method('isSuccessfulPaymentStatus')
            ->willReturn(false);
        $this->assertEmpty($this->handler->handle($context, 'some-payment-uuuid'));
    }
}
