<?php

namespace Payever\PayeverPayments\tests\unit\Service\Payment;

use Payever\ExternalIntegration\Payments\Enum\Status;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\PaymentDetailsEntity;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\PayeverPayments\PevrPayeverIntegration;
use Payever\PayeverPayments\Service\Payment\TransactionStatusService;
use Payever\PayeverPayments\Service\Management\OrderTotalsManager;
use Payever\PayeverPayments\Service\Management\OrderItemsManager;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Psr\Log\LoggerInterface;

class TransactionStatusServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var EntityRepositoryInterface&MockObject|MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $orderTransactionRepository;

    /**
     * @var OrderTransactionStateHandler&MockObject|MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $orderTransactionStateHandler;

    /**
     * @var EntityRepositoryInterface&MockObject|MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $stateRepository;

    /**
     * @var StateMachineRegistry&MockObject|MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $stateMachineRegistry;

    /**
     * @var EntityRepositoryInterface&MockObject|MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $orderRepository;

    /**
     * @var TransactionStatusService
     */
    private $service;

    /**
     * @var OrderTotalsManager&MockObject|MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $totalsManager;

    /**
     * @var OrderItemsManager&MockObject|MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $orderItemsManager;

    /**
     * @var LoggerInterface&MockObject|MockObject|\PHPUnit_Framework_MockObject_MockObject
     */
    private $logger;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->orderTransactionRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderTransactionStateHandler = $this->getMockBuilder(OrderTransactionStateHandler::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->stateRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->stateMachineRegistry = $this->getMockBuilder(StateMachineRegistry::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->totalsManager = $this->getMockBuilder(OrderTotalsManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->orderItemsManager = $this->getMockBuilder(OrderItemsManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        // State repository
        $this->stateRepository->expects($this->any())
            ->method('search')
            ->willReturn(
                $stateResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

        $stateResult->expects($this->any())
            ->method('first')
            ->willReturn(
                $paymentState = $this->getMockBuilder(StateMachineStateEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->service = new TransactionStatusService(
            $this->orderTransactionRepository,
            $this->orderTransactionStateHandler,
            $this->stateRepository,
            $this->stateMachineRegistry,
            $this->orderRepository,
            $this->totalsManager,
            $this->orderItemsManager,
            $this->logger,
            '6.4.14'
        );
    }

    public function testPersistTransactionStatusCaseNew()
    {
        /** @var MockObject|SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|RetrievePaymentResultEntity $payeverPayment */
        $payeverPayment = $this->getMockBuilder(RetrievePaymentResultEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getReference', 'getPaymentDetails', 'getStatus'])
            ->getMock();
        $salesChannelContext->expects($this->once())
            ->method('getContext')
            ->willReturn(
                $this->getMockBuilder(Context::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $payeverPayment->expects($this->any())
            ->method('getReference')
            ->willReturn('some-reference');
        $this->orderRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $order->expects($this->once())
            ->method('getTransactions')
            ->willReturn(
                $this->getMockBuilder(OrderTransactionCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->orderTransactionRepository->expects($this->any())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->any())
            ->method('first')
            ->willReturn(
                $orderTransactionEntity = $this->getMockBuilder(OrderTransactionEntity::class)->getMock()
            );
        $orderTransactionEntity->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([]);
        $payeverPayment->expects($this->once())
            ->method('getPaymentDetails')
            ->willReturn(
                $paymentDetails = $this->getMockBuilder(PaymentDetailsEntity::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getUsageText'])
                    ->getMock()
            );
        $paymentDetails->expects($this->once())
            ->method('getUsageText')
            ->willReturn('usage_text');
        $orderTransactionEntity->expects($this->any())
            ->method('getId')
            ->willReturn('some-id');
        $this->orderTransactionRepository->expects($this->any())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->any())
            ->method('first')
            ->willReturn($orderTransactionEntity);
        $payeverPayment->expects($this->any())
            ->method('getStatus')
            ->willReturn(Status::STATUS_NEW);
        $this->service->persistTransactionStatus($salesChannelContext, $payeverPayment);
    }

    public function testPersistTransactionStatusCaseInProcess()
    {
        /** @var MockObject|SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|RetrievePaymentResultEntity $payeverPayment */
        $payeverPayment = $this->getMockBuilder(RetrievePaymentResultEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getReference', 'getPaymentDetails', 'getStatus'])
            ->getMock();
        $salesChannelContext->expects($this->once())
            ->method('getContext')
            ->willReturn(
                $this->getMockBuilder(Context::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $payeverPayment->expects($this->any())
            ->method('getReference')
            ->willReturn('some-reference');
        $this->orderRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $order->expects($this->once())
            ->method('getTransactions')
            ->willReturn(
                $this->getMockBuilder(OrderTransactionCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->orderTransactionRepository->expects($this->any())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->any())
            ->method('first')
            ->willReturn(
                $orderTransactionEntity = $this->getMockBuilder(OrderTransactionEntity::class)->getMock()
            );
        $orderTransactionEntity->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([]);
        $payeverPayment->expects($this->any())
            ->method('getPaymentDetails')
            ->willReturn(
                $paymentDetails = $this->getMockBuilder(PaymentDetailsEntity::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getUsageText'])
                    ->getMock()
            );
        $paymentDetails->expects($this->once())
            ->method('getUsageText')
            ->willReturn('usage_text');
        $orderTransactionEntity->expects($this->any())
            ->method('getId')
            ->willReturn('some-id');
        $payeverPayment->expects($this->any())
            ->method('getStatus')
            ->willReturn(Status::STATUS_IN_PROCESS);
        $orderTransactionEntity->expects($this->any())
            ->method('getStateMachineState')
            ->willReturn(
                $stateMachineState = $this->getMockBuilder(StateMachineStateEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $stateMachineState->expects($this->once())
            ->method('getTechnicalName')
            ->willReturn('in_progress');
        $this->service->persistTransactionStatus($salesChannelContext, $payeverPayment);
    }

    public function testPersistTransactionStatusCaseAccepted()
    {
        /** @var MockObject|SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|RetrievePaymentResultEntity $payeverPayment */
        $payeverPayment = $this->getMockBuilder(RetrievePaymentResultEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getReference', 'getPaymentDetails', 'getStatus'])
            ->getMock();
        $salesChannelContext->expects($this->once())
            ->method('getContext')
            ->willReturn(
                $this->getMockBuilder(Context::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $payeverPayment->expects($this->any())
            ->method('getReference')
            ->willReturn('some-reference');
        $this->orderRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $order->expects($this->once())
            ->method('getTransactions')
            ->willReturn(
                $this->getMockBuilder(OrderTransactionCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->orderTransactionRepository->expects($this->any())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->any())
            ->method('first')
            ->willReturn(
                $orderTransactionEntity = $this->getMockBuilder(OrderTransactionEntity::class)->getMock()
            );
        $orderTransactionEntity->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([]);
        $payeverPayment->expects($this->any())
            ->method('getPaymentDetails')
            ->willReturn(
                $paymentDetails = $this->getMockBuilder(PaymentDetailsEntity::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getUsageText'])
                    ->getMock()
            );
        $paymentDetails->expects($this->once())
            ->method('getUsageText')
            ->willReturn('usage_text');
        $orderTransactionEntity->expects($this->any())
            ->method('getId')
            ->willReturn('some-id');
        $payeverPayment->expects($this->any())
            ->method('getStatus')
            ->willReturn(Status::STATUS_ACCEPTED);
        $orderTransactionEntity->expects($this->any())
            ->method('getStateMachineState')
            ->willReturn(
                $stateMachineState = $this->getMockBuilder(StateMachineStateEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $stateMachineState->expects($this->once())
            ->method('getTechnicalName')
            ->willReturn('paid');
        $this->service->persistTransactionStatus($salesChannelContext, $payeverPayment);
    }

    public function testPersistTransactionStatusCaseRefund()
    {
        /** @var MockObject|SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|RetrievePaymentResultEntity $payeverPayment */
        $payeverPayment = $this->getMockBuilder(RetrievePaymentResultEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getReference', 'getPaymentDetails', 'getStatus'])
            ->getMock();
        $salesChannelContext->expects($this->once())
            ->method('getContext')
            ->willReturn(
                $this->getMockBuilder(Context::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $payeverPayment->expects($this->any())
            ->method('getReference')
            ->willReturn('some-reference');
        $this->orderRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $order->expects($this->once())
            ->method('getTransactions')
            ->willReturn(
                $this->getMockBuilder(OrderTransactionCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->orderTransactionRepository->expects($this->any())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->any())
            ->method('first')
            ->willReturn(
                $orderTransactionEntity = $this->getMockBuilder(OrderTransactionEntity::class)->getMock()
            );
        $orderTransactionEntity->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([]);
        $payeverPayment->expects($this->any())
            ->method('getPaymentDetails')
            ->willReturn(
                $paymentDetails = $this->getMockBuilder(PaymentDetailsEntity::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getUsageText'])
                    ->getMock()
            );
        $paymentDetails->expects($this->once())
            ->method('getUsageText')
            ->willReturn('usage_text');
        $orderTransactionEntity->expects($this->any())
            ->method('getId')
            ->willReturn('some-id');
        $payeverPayment->expects($this->any())
            ->method('getStatus')
            ->willReturn(Status::STATUS_ACCEPTED);
        $orderTransactionEntity->expects($this->any())
            ->method('getStateMachineState')
            ->willReturn(
                $stateMachineState = $this->getMockBuilder(StateMachineStateEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $stateMachineState->expects($this->once())
            ->method('getTechnicalName')
            ->willReturn('paid');
        $this->service->persistTransactionStatus($salesChannelContext, $payeverPayment);
    }

    public function testPersistTransactionStatusCaseCancel()
    {
        /** @var MockObject|SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|RetrievePaymentResultEntity $payeverPayment */
        $payeverPayment = $this->getMockBuilder(RetrievePaymentResultEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getReference', 'getPaymentDetails', 'getStatus'])
            ->getMock();
        $salesChannelContext->expects($this->once())
            ->method('getContext')
            ->willReturn(
                $this->getMockBuilder(Context::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $payeverPayment->expects($this->any())
            ->method('getReference')
            ->willReturn('some-reference');
        $this->orderRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $order->expects($this->once())
            ->method('getTransactions')
            ->willReturn(
                $this->getMockBuilder(OrderTransactionCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->orderTransactionRepository->expects($this->any())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->any())
            ->method('first')
            ->willReturn(
                $orderTransactionEntity = $this->getMockBuilder(OrderTransactionEntity::class)->getMock()
            );
        $orderTransactionEntity->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([]);
        $payeverPayment->expects($this->any())
            ->method('getPaymentDetails')
            ->willReturn(
                $paymentDetails = $this->getMockBuilder(PaymentDetailsEntity::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getUsageText'])
                    ->getMock()
            );
        $paymentDetails->expects($this->once())
            ->method('getUsageText')
            ->willReturn('usage_text');
        $orderTransactionEntity->expects($this->any())
            ->method('getId')
            ->willReturn('some-id');
        $payeverPayment->expects($this->any())
            ->method('getStatus')
            ->willReturn(Status::STATUS_ACCEPTED);
        $orderTransactionEntity->expects($this->any())
            ->method('getStateMachineState')
            ->willReturn(
                $stateMachineState = $this->getMockBuilder(StateMachineStateEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $stateMachineState->expects($this->once())
            ->method('getTechnicalName')
            ->willReturn('paid');
        $this->service->persistTransactionStatus($salesChannelContext, $payeverPayment);
    }

    public function testPersistTransactionStatusCaseException()
    {
        /** @var MockObject|SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|RetrievePaymentResultEntity $payeverPayment */
        $payeverPayment = $this->getMockBuilder(RetrievePaymentResultEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getReference', 'getPaymentDetails', 'getStatus'])
            ->getMock();
        $salesChannelContext->expects($this->once())
            ->method('getContext')
            ->willReturn(
                $this->getMockBuilder(Context::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $payeverPayment->expects($this->any())
            ->method('getReference')
            ->willReturn('some-reference');
        $this->orderRepository->expects($this->any())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $order->expects($this->once())
            ->method('getTransactions')
            ->willReturn(
                $transactions = $this->getMockBuilder(OrderTransactionCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->orderTransactionRepository->expects($this->any())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->any())
            ->method('first')
            ->willReturn(
                $orderTransactionEntity = $this->getMockBuilder(OrderTransactionEntity::class)->getMock()
            );
        $orderTransactionEntity->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([]);
        $payeverPayment->expects($this->any())
            ->method('getPaymentDetails')
            ->willReturn(
                $paymentDetails = $this->getMockBuilder(PaymentDetailsEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentDetails->expects($this->once())
            ->method('__call')
            ->willReturn('usage_text');
        $orderTransactionEntity->expects($this->any())
            ->method('getId')
            ->willReturn('some-id');
        $payeverPayment->expects($this->any())
            ->method('getStatus')
            ->willReturn('unknown');
        $this->expectException(\RuntimeException::class);
        $this->service->persistTransactionStatus($salesChannelContext, $payeverPayment);
    }

    public function testShouldRejectNotification()
    {
        /** @var MockObject|SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $salesChannelContext->expects($this->once())
            ->method('getContext')
            ->willReturn(
                $this->getMockBuilder(Context::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->orderRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $order->expects($this->once())
            ->method('getTransactions')
            ->willReturn(
                $this->getMockBuilder(OrderTransactionCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->orderTransactionRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $orderTransactionEntity = $this->getMockBuilder(OrderTransactionEntity::class)->getMock()
            );
        $orderTransactionEntity->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([PevrPayeverIntegration::CUSTOM_FIELD_NOTIFICATION_TIMESTAMP => 1]);
        $this->assertTrue(
            $this->service->shouldRejectNotification('some-reference', 0, $salesChannelContext)
        );
    }

    public function testUpdateNotificationTimestamp()
    {
        /** @var MockObject|SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $salesChannelContext->expects($this->once())
            ->method('getContext')
            ->willReturn(
                $this->getMockBuilder(Context::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->orderRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $order->expects($this->once())
            ->method('getTransactions')
            ->willReturn(
                $this->getMockBuilder(OrderTransactionCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->orderTransactionRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $orderTransactionEntity = $this->getMockBuilder(OrderTransactionEntity::class)->getMock()
            );
        $orderTransactionEntity->expects($this->once())
            ->method('getId')
            ->willReturn('some-id');
        $this->orderTransactionRepository->expects($this->once())
            ->method('update');
        $this->service->updateNotificationTimestamp(
            'some-reference',
            0,
            $salesChannelContext
        );
    }

    public function testUpdateTransactionCustomFields()
    {
        $this->orderTransactionRepository->expects($this->once())
            ->method('update');
        $this->service->updateTransactionCustomFields('some-reference', []);
    }

    public function testGetOrderTransactionById()
    {
        /** @var MockObject|Context $context */
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderTransactionRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $this->getMockBuilder(OrderTransactionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->assertNotEmpty($this->service->getOrderTransactionById($context, 'some-order-id'));
    }

    public function testCancelOrderTransaction()
    {
        /** @var MockObject|Context $context */
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderTransactionStateHandler->expects($this->once())
            ->method('cancel');
        $this->service->cancelOrderTransaction($context, 'some-transaction-id');
    }

    public function testGetNotFinishedTransactions()
    {
        /** @var MockObject|Context $context */
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->stateMachineRegistry->expects($this->once())
            ->method('getInitialState')
            ->willReturn(
                $this->getMockBuilder(StateMachineStateEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->orderTransactionRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->service->getNotFinishedTransactions([], $context);
    }

    public function testIsSuccessfulPaymentStatus()
    {
        $this->assertTrue($this->service->isSuccessfulPaymentStatus('STATUS_IN_PROCESS'));
    }
}
