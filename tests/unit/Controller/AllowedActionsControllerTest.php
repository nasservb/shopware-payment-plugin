<?php

namespace Payever\PayeverPayments\tests\unit\Controller;

use Payever\ExternalIntegration\Core\Base\ResponseInterface;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\GetTransactionResultEntity;
use Payever\ExternalIntegration\Payments\Http\ResponseEntity\GetTransactionResponse;
use Payever\ExternalIntegration\Payments\PaymentsApiClient;
use Payever\PayeverPayments\Controller\AllowedActionsController;
use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

class AllowedActionsControllerTest extends \PHPUnit\Framework\TestCase
{
    /** @var MockObject|ContainerInterface */
    private $container;

    /** @var MockObject|ClientFactory */
    private $apiClientFactory;

    /** @var AllowedActionsController */
    private $controller;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->apiClientFactory = $this->getMockBuilder(ClientFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->controller = new AllowedActionsController($this->apiClientFactory);
        $this->controller->setContainer($this->container);
    }

    public function testGetAllowedActionsLegacy()
    {
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) {
                if ('transactionId' === $key) {
                    return 'some-transaction-id';
                }
                if ('salesChannelId' === $key) {
                    return 'some-sales-channel-id';
                }
            });
        $this->apiClientFactory->expects($this->once())
            ->method('getPaymentsApiClient')
            ->willReturn(
                $paymentsApiClient = $this->getMockBuilder(PaymentsApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentsApiClient->expects($this->once())
            ->method('getTransactionRequest')
            ->willReturn(
                $getTransactionResponse = $this->getMockBuilder(ResponseInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $getTransactionResponse->expects($this->once())
            ->method('getResponseEntity')
            ->willReturn(
                $getTransactionEntity = $this->getMockBuilder(GetTransactionResponse::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $getTransactionEntity->expects($this->once())
            ->method('__call')
            ->willReturn(
                $getTransactionResult = $this->getMockBuilder(GetTransactionResultEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $getTransactionResult->expects($this->once())
            ->method('__call')
            ->willReturn([
                (object) [
                    'action' => 'some-action',
                    'enabled' => true,
                ]
            ]);
        $this->assertNotEmpty($this->controller->getAllowedActionsLegacy($request));
    }

    public function testGetAllowedActions()
    {
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) {
                if ('transactionId' === $key) {
                    return 'some-transaction-id';
                }
                if ('salesChannelId' === $key) {
                    return 'some-sales-channel-id';
                }
            });
        $this->apiClientFactory->expects($this->once())
            ->method('getPaymentsApiClient')
            ->willReturn(
                $paymentsApiClient = $this->getMockBuilder(PaymentsApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentsApiClient->expects($this->once())
            ->method('getTransactionRequest')
            ->willReturn(
                $getTransactionResponse = $this->getMockBuilder(ResponseInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $getTransactionResponse->expects($this->once())
            ->method('getResponseEntity')
            ->willReturn(
                $getTransactionEntity = $this->getMockBuilder(GetTransactionResponse::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $getTransactionEntity->expects($this->once())
            ->method('__call')
            ->willReturn(
                $getTransactionResult = $this->getMockBuilder(GetTransactionResultEntity::class)
                ->disableOriginalConstructor()
                ->getMock()
            );
        $getTransactionResult->expects($this->once())
            ->method('__call')
            ->willReturn([
                (object) [
                    'action' => 'some-action',
                    'enabled' => true,
                ]
            ]);
        $this->assertNotEmpty($this->controller->getAllowedActions($request));
    }

    public function testGetAllowedActionsCaseException()
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
                if ('transactionId' === $key) {
                    return 'some-transaction-id';
                }
                if ('salesChannelId' === $key) {
                    return 'some-sales-channel-id';
                }
            });
        $this->apiClientFactory->expects($this->once())
            ->method('getPaymentsApiClient')
            ->willReturn(
                $paymentsApiClient = $this->getMockBuilder(PaymentsApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentsApiClient->expects($this->once())
            ->method('getTransactionRequest')
            ->willThrowException(new \Exception());
        $this->assertNotEmpty($this->controller->getAllowedActions($request, $context));
    }
}
