<?php

namespace Payever\PayeverPayments\tests\unit\Service\Payment\FinanceExpress;

use Payever\ExternalIntegration\Core\Lock\LockInterface;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\ExternalIntegration\Payments\Http\RequestEntity\NotificationRequestEntity;
use Payever\ExternalIntegration\Payments\Notification\NotificationResult;
use Payever\PayeverPayments\Service\Helper\SalesChannelContextHelper;
use Payever\PayeverPayments\Service\PayeverPayment;
use Payever\PayeverPayments\Service\Payment\FinanceExpress\CustomerHelper;
use Payever\PayeverPayments\Service\Payment\FinanceExpress\NotificationHandler;
use Payever\PayeverPayments\Service\Payment\FinanceExpress\OrderHelper;
use Payever\PayeverPayments\Service\Payment\TransactionStatusService;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class NotificationHandlerTest extends \PHPUnit\Framework\TestCase
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
     * @var MockObject|SalesChannelContextHelper
     */
    private $salesChannelContextHelper;

    /**
     * @var NotificationHandler
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
        $this->salesChannelContextHelper = $this->getMockBuilder(SalesChannelContextHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->handler = new NotificationHandler(
            $this->paymentHandler,
            $this->transactionStatusService,
            $this->customerHelper,
            $this->orderHelper,
            $this->locker,
            $this->logger,
            $this->salesChannelContextHelper
        );
    }

    public function testHandleNotification()
    {
        /** @var MockObject|NotificationRequestEntity $notification */
        $notification = $this->getMockBuilder(NotificationRequestEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getPayment'])
            ->getMock();
        /** @var MockObject|NotificationResult $notificationResult */
        $notificationResult = $this->getMockBuilder(NotificationResult::class)
            ->disableOriginalConstructor()
            ->getMock();
        $notification->expects($this->once())
            ->method('getPayment')
            ->willReturn(
                $paymentResult = $this->getMockBuilder(RetrievePaymentResultEntity::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getStatus', 'getReference'])
                    ->getMock()
            );
        $paymentResult->expects($this->once())
            ->method('getStatus')
            ->willReturn('STATUS_IN_PROCESS');
        $this->transactionStatusService->expects($this->once())
            ->method('isSuccessfulPaymentStatus')
            ->willReturn(true);
        $this->salesChannelContextHelper->expects($this->once())
            ->method('getSalesChannelContext')
            ->willReturn(
                $this->getMockBuilder(SalesChannelContext::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
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
                $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentResult->expects($this->once())
            ->method('getReference')
            ->willReturn('some-reference');
        $this->transactionStatusService->expects($this->once())
            ->method('shouldRejectNotification')
            ->willReturn(false);
        $this->transactionStatusService->expects($this->once())
            ->method('persistTransactionStatus');
        $this->transactionStatusService->expects($this->once())
            ->method('updateNotificationTimestamp');
        $this->handler->handleNotification($notification, $notificationResult);
    }

    public function testHandleNotificationCaseUnsuccessfulPaymentStatus()
    {
        /** @var MockObject|NotificationRequestEntity $notification */
        $notification = $this->getMockBuilder(NotificationRequestEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getPayment'])
            ->getMock();
        /** @var MockObject|NotificationResult $notificationResult */
        $notificationResult = $this->getMockBuilder(NotificationResult::class)
            ->disableOriginalConstructor()
            ->getMock();
        $notification->expects($this->once())
            ->method('getPayment')
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
        $this->transactionStatusService->expects($this->never())
            ->method('persistTransactionStatus');
        $this->handler->handleNotification($notification, $notificationResult);
    }

    public function testHandleNotificationCaseRejectNotification()
    {
        /** @var MockObject|NotificationRequestEntity $notification */
        $notification = $this->getMockBuilder(NotificationRequestEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getPayment', 'getCreatedAt'])
            ->getMock();
        /** @var MockObject|NotificationResult $notificationResult */
        $notificationResult = $this->getMockBuilder(NotificationResult::class)
            ->disableOriginalConstructor()
            ->getMock();
        $notification->expects($this->once())
            ->method('getPayment')
            ->willReturn(
                $paymentResult = $this->getMockBuilder(RetrievePaymentResultEntity::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getStatus', 'getReference'])
                    ->getMock()
            );
        $paymentResult->expects($this->once())
            ->method('getStatus')
            ->willReturn('STATUS_IN_PROCESS');
        $this->transactionStatusService->expects($this->once())
            ->method('isSuccessfulPaymentStatus')
            ->willReturn(true);
        $this->salesChannelContextHelper->expects($this->once())
            ->method('getSalesChannelContext')
            ->willReturn(
                $this->getMockBuilder(SalesChannelContext::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
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
                $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentResult->expects($this->once())
            ->method('getReference')
            ->willReturn('some-reference');
        $notification->expects($this->once())
            ->method('getCreatedAt')
            ->willReturn(new \DateTime('-1day'));
        $this->transactionStatusService->expects($this->once())
            ->method('shouldRejectNotification')
            ->willReturn(true);
        $this->transactionStatusService->expects($this->never())
            ->method('persistTransactionStatus');
        $this->handler->handleNotification($notification, $notificationResult);
    }
}
