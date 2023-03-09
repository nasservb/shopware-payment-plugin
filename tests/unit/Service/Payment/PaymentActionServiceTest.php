<?php

namespace Payever\PayeverPayments\tests\unit\Service\Payment;

use Payever\ExternalIntegration\Core\Base\ResponseInterface;
use Payever\ExternalIntegration\Payments\Action\ActionDecider;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\GetTransactionResultEntity;
use Payever\ExternalIntegration\Payments\Http\ResponseEntity\GetTransactionResponse;
use Payever\ExternalIntegration\Payments\PaymentsApiClient;
use Payever\ExternalIntegration\Payments\Http\ResponseEntity\ShippingGoodsPaymentResponse;
use Payever\ExternalIntegration\Payments\Http\ResponseEntity\RefundPaymentResponse;
use Payever\ExternalIntegration\Payments\Http\ResponseEntity\CancelPaymentResponse;
use Payever\PayeverPayments\PevrPayeverIntegration;
use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use Payever\PayeverPayments\Service\Payment\PaymentActionService;
use Payever\PayeverPayments\Service\Management\OrderTotalsManager;
use Payever\PayeverPayments\Service\Management\OrderItemsManager;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use PHPUnit\Framework\MockObject\MockObject;

class PaymentActionServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ClientFactory&MockObject|MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $apiClientFactory;

    /**
     * @var EntityRepositoryInterface&MockObject|MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $orderRepository;

    /**
     * @var EntityRepositoryInterface&MockObject|MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $orderTransactionRepository;

    /**
     * @var EntityRepositoryInterface&MockObject|MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $orderDeliveryRepository;

    /**
     * @var EntityRepositoryInterface&MockObject|MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $stateRepository;

    /**
     * @var PaymentActionService&MockObject|MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $service;

    /**
     * @var OrderService&MockObject|MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $orderService;

    /**
     * @var StateMachineRegistry&MockObject|MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $stateMachineRegistry;

    /**
     * @var OrderTotalsManager&MockObject|MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $totalsManager;

    /**
     * @var OrderItemsManager&MockObject|MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $orderItemsManager;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->apiClientFactory = $this->getMockBuilder(ClientFactory::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderTransactionRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderDeliveryRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->stateRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderService = $this->getMockBuilder(OrderService::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->stateMachineRegistry = $this->getMockBuilder(StateMachineRegistry::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->totalsManager = $this->getMockBuilder(OrderTotalsManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderItemsManager = $this->getMockBuilder(OrderItemsManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        // Order repository
        $this->orderRepository->expects($this->any())
            ->method('search')
            ->willReturn(
                $searchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

        $searchResult->expects($this->any())
            ->method('first')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

        // Order transaction repository
        $this->orderTransactionRepository->expects($this->any())
            ->method('search')
            ->willReturn(
                $searchOrderTransactionResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

        $searchOrderTransactionResult->expects($this->any())
            ->method('first')
            ->willReturn(
                $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

        // Order delivery repository
        $this->orderDeliveryRepository->expects($this->any())
            ->method('search')
            ->willReturn(
                $searchOrderDeliveryResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

        $searchOrderDeliveryResult->expects($this->any())
            ->method('first')
            ->willReturn(
                $orderDelivery = $this->getMockBuilder(OrderDeliveryEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

        $this->service = new PaymentActionService(
            $this->apiClientFactory,
            $this->orderRepository,
            $this->orderTransactionRepository,
            $this->orderDeliveryRepository,
            $this->stateRepository,
            $this->orderService,
            $this->stateMachineRegistry,
            $this->totalsManager,
            $this->orderItemsManager
        );
    }

    public function testRefundTransaction()
    {
        /** @var MockObject|OrderTransactionEntity $orderTransaction */
        $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orderTransaction->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([
                PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_ID => 'some-uuid',
                PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_AMOUNT => 0.0,
            ]);
        $orderTransaction->expects($this->any())
            ->method('getOrder')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->apiClientFactory->expects($this->once())
            ->method('getPaymentsApiClient')
            ->willReturn(
                $paymentsApiClient = $this->getMockBuilder(PaymentsApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentsApiClient->expects($this->any())
            ->method('getTransactionRequest')
            ->willReturn(
                $response = $this->getMockBuilder(ResponseInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $response->expects($this->once())
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
                $action = new \stdClass()
            ]);
        $action->action = 'refund';
        $action->enabled = true;

        $paymentsApiClient->expects($this->once())
            ->method('refundPaymentRequest')
            ->willReturn(
                $paymentResponse = $this->getMockBuilder(RefundPaymentResponse::class)
                                     ->disableOriginalConstructor()
                                     ->getMock()
            );

        $this->totalsManager->expects($this->once())
            ->method('addRefunded');

        $this->service->refundTransaction($orderTransaction, 0.0);
    }

    public function testRefundTransactionCaseDisallowed()
    {
        /** @var MockObject|OrderTransactionEntity $orderTransaction */
        $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orderTransaction->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([
                PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_ID => 'some-uuid',
                PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_AMOUNT => 0.0,
            ]);
        $orderTransaction->expects($this->any())
            ->method('getOrder')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
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
                $response = $this->getMockBuilder(ResponseInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $response->expects($this->once())
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
                $action = new \stdClass()
            ]);
        $action->action = ActionDecider::ACTION_RETURN;
        $action->enabled = false;
        $paymentsApiClient->expects($this->never())
            ->method('refundPaymentRequest');

        //Refund request failed: Refund action is not available
        $this->expectException(\RuntimeException::class);
        $this->service->refundTransaction($orderTransaction, 0.0);
    }

    public function testRefundTransactionCaseException()
    {
        $this->expectException(\RuntimeException::class);
        /** @var MockObject|OrderTransactionEntity $orderTransaction */
        $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orderTransaction->expects($this->once())
            ->method('getCustomFields')
            ->willThrowException(new \Exception());
        $this->service->refundTransaction($orderTransaction, 0.0);
    }

    public function testCancelTransaction()
    {
        /** @var MockObject|OrderTransactionEntity $orderTransaction */
        $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $response = $this->getMockBuilder(ResponseInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orderTransaction->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([
                PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_ID => 'some-uuid',
            ]);
        $orderTransaction->expects($this->any())
            ->method('getOrder')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->apiClientFactory->expects($this->any())
            ->method('getPaymentsApiClient')
            ->willReturn(
                $paymentsApiClient = $this->getMockBuilder(PaymentsApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentsApiClient->expects($this->once())
            ->method('getTransactionRequest')
            ->willReturn($response);
        $response->expects($this->any())
            ->method('getResponseEntity')
            ->willReturn(
                $getTransactionEntity = $this->getMockBuilder(GetTransactionResponse::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $getTransactionEntity->expects($this->any())
            ->method('__call')
            ->willReturn(
                $getTransactionResult = $this->getMockBuilder(GetTransactionResultEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $getTransactionResult->expects($this->any())
            ->method('__call')
            ->willReturn([
                $action = new \stdClass()
            ]);
        $action->action = ActionDecider::ACTION_CANCEL;
        $action->enabled = true;

        $paymentsApiClient->expects($this->once())
            ->method('cancelPaymentRequest')
            ->willReturn($response = $this->getMockBuilder(CancelPaymentResponse::class)
                                          ->disableOriginalConstructor()
                                          ->getMock());

        $this->totalsManager->expects($this->once())
                       ->method('addCancelled');

        $this->service->cancelTransaction($orderTransaction);
    }

    public function testCancelTransactionCaseDisallowed()
    {
        /** @var MockObject|OrderTransactionEntity $orderTransaction */
        $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orderTransaction->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([
                PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_ID => 'some-uuid',
            ]);
        $orderTransaction->expects($this->any())
            ->method('getOrder')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
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
                $response = $this->getMockBuilder(ResponseInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $response->expects($this->once())
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
                $action = new \stdClass()
            ]);
        $action->action = ActionDecider::ACTION_CANCEL;
        $action->enabled = false;
        $paymentsApiClient->expects($this->never())
            ->method('cancelPaymentRequest');

        //Cancel failed: Cancel action is not available.
        $this->expectException(\RuntimeException::class);
        $this->service->cancelTransaction($orderTransaction);
    }

    public function testCancelTransactionCaseException()
    {
        $this->expectException(\RuntimeException::class);
        /** @var MockObject|OrderTransactionEntity $orderTransaction */
        $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orderTransaction->expects($this->once())
            ->method('getCustomFields')
            ->willThrowException(new \Exception());
        $this->service->cancelTransaction($orderTransaction);
    }

    public function testShippingTransaction()
    {
        /** @var MockObject|OrderTransactionEntity $orderTransaction */
        $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orderTransaction->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([
                PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_ID => 'some-uuid',
                PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_AMOUNT => 0.0,
            ]);
        $orderTransaction->expects($this->any())
            ->method('getOrder')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $order->expects($this->once())
            ->method('getDeliveries')
            ->willReturn(
                $orderDeliveries = $this->getMockBuilder(OrderDeliveryCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderDeliveries->expects($this->once())
            ->method('count')
            ->willReturn(0);
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
                $response = $this->getMockBuilder(ResponseInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $response->expects($this->once())
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
                $action = new \stdClass()
            ]);

        $action->action = ActionDecider::ACTION_SHIPPING_GOODS;
        $action->enabled = true;

        $paymentsApiClient->expects($this->once())
                          ->method('shippingGoodsPaymentRequest')
                          ->willReturn(
                              $response = $this->getMockBuilder(ShippingGoodsPaymentResponse::class)
                                               ->disableOriginalConstructor()
                                               ->getMock()
                          );

        $this->totalsManager->expects($this->once())
                            ->method('addCaptured');


        $this->service->shippingTransaction($orderTransaction, 1.1);
    }

    public function testShippingTransactionCaseDisallowed()
    {
        /** @var MockObject|OrderTransactionEntity $orderTransaction */
        $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orderTransaction->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([
                PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_ID => 'some-uuid',
                PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_AMOUNT => 0.0,
            ]);
        $orderTransaction->expects($this->any())
            ->method('getOrder')
            ->willReturn(
                $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
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
                $response = $this->getMockBuilder(ResponseInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $response->expects($this->once())
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
                $action = new \stdClass()
            ]);
        $action->action = ActionDecider::ACTION_SHIPPING_GOODS;
        $action->enabled = false;
        $paymentsApiClient->expects($this->never())
            ->method('shippingGoodsPaymentRequest');

        // Shipping goods action failed: Shipping goods is not available.
        $this->expectException(\RuntimeException::class);
        $this->service->shippingTransaction($orderTransaction, 1.1);
    }

    public function testShippingTransactionCaseException()
    {
        $this->expectException(\RuntimeException::class);
        /** @var MockObject|OrderTransactionEntity $orderTransaction */
        $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $orderTransaction->expects($this->once())
            ->method('getCustomFields')
            ->willThrowException(new \Exception());
        $this->service->shippingTransaction($orderTransaction, 1.1);
    }

    public function testShipGoodsTransaction()
    {
        /** @var MockObject|OrderTransactionEntity $orderTransaction */
        $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderTransaction->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([
                PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_ID => 'some-uuid',
                PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_AMOUNT => 0.0,
            ]);

        $orderTransaction->expects($this->any())
            ->method('getOrder')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

        $this->apiClientFactory->expects($this->once())
            ->method('getPaymentsApiClient')
            ->willReturn(
                $paymentsApiClient = $this->getMockBuilder(PaymentsApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

        $paymentsApiClient->expects($this->any())
            ->method('getTransactionRequest')
            ->willReturn(
                $response = $this->getMockBuilder(ResponseInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $response->expects($this->any())
            ->method('getResponseEntity')
            ->willReturn(
                $getTransactionEntity = $this->getMockBuilder(GetTransactionResponse::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $getTransactionEntity->expects($this->any())
            ->method('__call')
            ->willReturn(
                $getTransactionResult = $this->getMockBuilder(GetTransactionResultEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $getTransactionResult->expects($this->any())
            ->method('__call')
            ->willReturn([
                $action = new \stdClass()
            ]);

        $action->action = ActionDecider::ACTION_SHIPPING_GOODS;
        $action->enabled = true;
        $action->partialAllowed = true;

        $paymentsApiClient->expects($this->once())
            ->method('shippingGoodsPaymentRequest')
            ->willReturn(
                $response = $this->getMockBuilder(ShippingGoodsPaymentResponse::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

        $this->totalsManager->expects($this->once())
            ->method('addCaptured');

        $this->service->shipGoodsTransaction($orderTransaction, []);
    }

    public function testRefundItemTransaction()
    {
        /** @var MockObject|OrderTransactionEntity $orderTransaction */
        $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderTransaction->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([
                PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_ID => 'some-uuid',
                PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_AMOUNT => 0.0,
            ]);

        $orderTransaction->expects($this->any())
            ->method('getOrder')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

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
                $response = $this->getMockBuilder(ResponseInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $response->expects($this->once())
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
                $action = new \stdClass()
            ]);

        $action->action = ActionDecider::ACTION_REFUND;
        $action->enabled = true;
        $action->partialAllowed = true;

        $paymentsApiClient->expects($this->once())
            ->method('refundItemsPaymentRequest')
            ->willReturn(
                $response = $this->getMockBuilder(RefundPaymentResponse::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

        $this->totalsManager->expects($this->once())
            ->method('addRefunded');

        $this->service->refundItemTransaction($orderTransaction, []);
    }

    public function testCancelItemTransaction()
    {
        $this->totalsManager->expects($this->any())
            ->method('getAvailableForCancelling')
            ->willReturn(100.1);

        /** @var MockObject|OrderTransactionEntity $orderTransaction */
        $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
            ->disableOriginalConstructor()
            ->getMock();

        $orderTransaction->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([
                PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_ID => 'some-uuid',
                PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_AMOUNT => 0.0,
            ]);

        $orderTransaction->expects($this->any())
            ->method('getOrder')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

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
                $response = $this->getMockBuilder(ResponseInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $response->expects($this->once())
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
                $action = new \stdClass()
            ]);

        $action->action = ActionDecider::ACTION_CANCEL;
        $action->enabled = true;
        $action->partialAllowed = true;

        $paymentsApiClient->expects($this->once())
            ->method('cancelItemsPaymentRequest')
            ->willReturn(
                $response = $this->getMockBuilder(CancelPaymentResponse::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

        $this->totalsManager->expects($this->once())
            ->method('addCancelled');

        $this->service->cancelItemTransaction($orderTransaction, []);
    }

    public function testAssertOrderCaseException()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('assertOrder');
        $method->setAccessible(true);

        $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->expectException(\RuntimeException::class);
        $method->invoke($this->service, $orderTransaction);
    }
}
