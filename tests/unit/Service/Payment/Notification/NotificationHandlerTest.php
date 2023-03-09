<?php

namespace Payever\PayeverPayments\tests\unit\Service\Payment\Notification;

use Payever\ExternalIntegration\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\ExternalIntegration\Payments\Http\RequestEntity\NotificationRequestEntity;
use Payever\ExternalIntegration\Payments\Notification\NotificationResult;
use Payever\PayeverPayments\Service\Helper\SalesChannelContextHelper;
use Payever\PayeverPayments\Service\Payment\Notification\NotificationHandler;
use Payever\PayeverPayments\Service\Payment\TransactionStatusService;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class NotificationHandlerTest extends \PHPUnit\Framework\TestCase
{
    /** @var MockObject|TransactionStatusService */
    private $transactionStatusService;

    /** @var MockObject|SalesChannelContextHelper */
    private $salesChannelContextHelper;

    /** @var NotificationHandler */
    private $handler;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->transactionStatusService = $this->getMockBuilder(TransactionStatusService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->salesChannelContextHelper = $this->getMockBuilder(SalesChannelContextHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->handler = new NotificationHandler(
            $this->transactionStatusService,
            $this->salesChannelContextHelper
        );
    }

    public function testHandleNotification()
    {
        $this->salesChannelContextHelper->expects($this->once())
            ->method('getSalesChannelContext')
            ->willReturn(
                $this->getMockBuilder(SalesChannelContext::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
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
                $retrievePaymentResultEntity = $this->getMockBuilder(RetrievePaymentResultEntity::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getReference'])
                    ->getMock()
            );
        $retrievePaymentResultEntity->expects($this->once())
            ->method('getReference')
            ->willReturn('some-order-transaction-id');
        $notification->expects($this->once())
            ->method('getCreatedAt')
            ->willReturn(new \DateTime());
        $this->transactionStatusService->expects($this->once())
            ->method('shouldRejectNotification')
            ->willReturn(false);
        $this->handler->handleNotification($notification, $notificationResult);
    }

    public function testHandleNotificationCaseReject()
    {
        $this->salesChannelContextHelper->expects($this->once())
            ->method('getSalesChannelContext')
            ->willReturn(
                $this->getMockBuilder(SalesChannelContext::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
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
                $retrievePaymentResultEntity = $this->getMockBuilder(RetrievePaymentResultEntity::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getReference'])
                    ->getMock()
            );
        $retrievePaymentResultEntity->expects($this->once())
            ->method('getReference')
            ->willReturn('some-order-transaction-id');
        $notification->expects($this->once())
            ->method('getCreatedAt')
            ->willReturn(new \DateTime());
        $this->transactionStatusService->expects($this->once())
            ->method('shouldRejectNotification')
            ->willReturn(true);
        $this->handler->handleNotification($notification, $notificationResult);
    }
}
