<?php

namespace Payever\PayeverPayments\tests\unit\Service\Payment\Notification;

use Payever\ExternalIntegration\Core\Base\ResponseInterface;
use Payever\ExternalIntegration\Core\Http\MessageEntity\ResultEntity;
use Payever\ExternalIntegration\Core\Http\ResponseEntity;
use Payever\ExternalIntegration\Core\Lock\LockInterface;
use Payever\ExternalIntegration\Payments\Notification\NotificationHandlerInterface;
use Payever\ExternalIntegration\Payments\PaymentsApiClient;
use Payever\PayeverPayments\Service\Helper\SalesChannelContextHelper;
use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use Payever\PayeverPayments\Service\Payment\Notification\NotificationRequestProcessor;
use Payever\PayeverPayments\Service\Setting\PayeverSettingGeneralStruct;
use Payever\PayeverPayments\Service\Setting\SettingsServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class NotificationRequestProcessorTest extends \PHPUnit\Framework\TestCase
{
    /** @var MockObject|NotificationHandlerInterface */
    private $handler;

    /** @var MockObject|LockInterface */
    private $lock;

    /** @var MockObject|LoggerInterface */
    private $logger;

    /** @var MockObject|ClientFactory */
    private $apiClientFactory;

    /** @var MockObject|SettingsServiceInterface */
    private $settingsService;

    /** @var MockObject|SalesChannelContextHelper */
    private $salesChannelContextHelper;

    /** @var NotificationRequestProcessor */
    private $processor;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->handler = $this->getMockBuilder(NotificationHandlerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->lock = $this->getMockBuilder(LockInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->apiClientFactory = $this->getMockBuilder(ClientFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->settingsService = $this->getMockBuilder(SettingsServiceInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->salesChannelContextHelper = $this->getMockBuilder(SalesChannelContextHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->processor = new NotificationRequestProcessor(
            $this->handler,
            $this->lock,
            $this->logger,
            $this->apiClientFactory,
            $this->settingsService,
            $this->salesChannelContextHelper
        );
    }

    public function testProcessNotification()
    {
        $this->salesChannelContextHelper->expects($this->once())
            ->method('getRequest')
            ->willReturn(
                $request = $this->getMockBuilder(Request::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $request->query = $query = $this->getMockBuilder(ParameterBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query->expects($this->once())
            ->method('get')
            ->willReturn($paymentId = 'some-payment-id');
        $request->headers = $this->getMockBuilder(HeaderBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->salesChannelContextHelper->expects($this->once())
            ->method('getSalesChannelContext')
            ->willReturn(
                $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $salesChannelContext->expects($this->once())
            ->method('getSalesChannel')
            ->willReturn(
                $salesChannelEntity = $this->getMockBuilder(SalesChannelEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $salesChannelEntity->expects($this->once())
            ->method('getId')
            ->willReturn('some-sales-channel-id');
        $this->apiClientFactory->expects($this->once())
            ->method('getPaymentsApiClient')
            ->willReturn(
                $paymentsApiClient = $this->getMockBuilder(PaymentsApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentsApiClient->expects($this->once())
            ->method('retrievePaymentRequest')
            ->willReturn(
                $response = $this->getMockBuilder(ResponseInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $response->expects($this->once())
            ->method('getResponseEntity')
            ->willReturn(
                $responseEntity = $this->getMockBuilder(ResponseEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $responseEntity->expects($this->once())
            ->method('__call')
            ->willReturn(
                $resultEntity = $this->getMockBuilder(ResultEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $resultEntity->expects($this->once())
            ->method('toArray')
            ->willReturn([
                'id' => $paymentId,
            ]);
        $this->assertNotEmpty($this->processor->processNotification());
    }

    public function testProcessNotificationCaseSignature()
    {
        $this->salesChannelContextHelper->expects($this->once())
            ->method('getRequest')
            ->willReturn(
                $request = $this->getMockBuilder(Request::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $request->query = $query = $this->getMockBuilder(ParameterBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query->expects($this->once())
            ->method('get')
            ->willReturn($paymentId = 'some-payment-id');
        $request->headers = $headerBag = $this->getMockBuilder(HeaderBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $clientId = 'some-client-id';
        $clientSecret = 'some-client-secret';
        $headerBag->expects($this->once())
            ->method('get')
            ->willReturn(
                hash_hmac(
                    'sha256',
                    $clientId . $paymentId,
                    $clientSecret
                )
            );
        $this->salesChannelContextHelper->expects($this->once())
            ->method('getSalesChannelContext')
            ->willReturn(
                $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $settings = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $settings->expects($this->once())
            ->method('getClientId')
            ->willReturn($clientId);
        $settings->expects($this->once())
            ->method('getClientSecret')
            ->willReturn($clientSecret);
        $request->expects($this->once())
            ->method('getContent')
            ->willReturn(\json_encode([
                'data' => [
                    'payment' => [
                        'id' => $paymentId,
                    ],
                ],
            ]));
        $this->assertNotEmpty($this->processor->processNotification());
    }

    public function testProcessNotificationCaseSignatureDoesNotMatch()
    {
        $this->salesChannelContextHelper->expects($this->once())
            ->method('getRequest')
            ->willReturn(
                $request = $this->getMockBuilder(Request::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $request->query = $query = $this->getMockBuilder(ParameterBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query->expects($this->once())
            ->method('get')
            ->willReturn($paymentId = 'some-payment-id');
        $request->headers = $headerBag = $this->getMockBuilder(HeaderBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $headerBag->expects($this->once())
            ->method('get')
            ->willReturn(
                hash_hmac(
                    'sha256',
                    'some-client-id' . $paymentId,
                    'some-client-secret'
                )
            );
        $this->salesChannelContextHelper->expects($this->once())
            ->method('getSalesChannelContext')
            ->willReturn(
                $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $settings = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $settings->expects($this->once())
            ->method('getClientSecret')
            ->willReturn('some-other-client-secret');
        $this->expectException(\BadMethodCallException::class);
        $this->assertNotEmpty($this->processor->processNotification());
    }
}
