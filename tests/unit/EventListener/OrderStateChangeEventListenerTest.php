<?php

namespace Payever\PayeverPayments\tests\unit\EventListener;

use Payever\PayeverPayments\EventListener\OrderStateChangeEventListener;
use Payever\PayeverPayments\PevrPayeverIntegration;
use Payever\PayeverPayments\Service\Payment\PaymentActionService;
use Payever\PayeverPayments\Service\Management\OrderTotalsManager;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\System\StateMachine\Transition;

class OrderStateChangeEventListenerTest extends \PHPUnit\Framework\TestCase
{
    /** @var MockObject|EntityRepositoryInterface */
    private $orderRepository;

    /** @var MockObject|PaymentActionService */
    private $payeverTriggersHandler;

    /** @var OrderTotalsManager&MockObject|MockObject|\PHPUnit_Framework_MockObject_MockObject */
    private $totalsManager;

    /** @var OrderStateChangeEventListener */
    private $listener;

    /** @var OrderConverter */
    private $orderConverter;

    /** @var EntityRepositoryInterface */
    private $transactionRepository;
    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->orderRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->payeverTriggersHandler = $this->getMockBuilder(PaymentActionService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->totalsManager = $this->getMockBuilder(OrderTotalsManager::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->transactionRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderConverter = $this->getMockBuilder(OrderConverter::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->listener = new OrderStateChangeEventListener(
            $this->payeverTriggersHandler,
            $this->orderRepository,
            $this->totalsManager,
            $this->transactionRepository,
            $this->orderConverter
        );
    }

    public function testGetSubscribedEvents()
    {
        $this->assertNotEmpty($this->listener->getSubscribedEvents());
    }

    public function testOnOrderStateChangedCaseCancelled()
    {
        /** @var MockObject|StateMachineStateChangeEvent $event */
        $event = $this->getMockBuilder(StateMachineStateChangeEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getTransitionSide')
            ->willReturn(StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER);
        $event->expects($this->once())
            ->method('getTransition')
            ->willReturn(
                $transition = $this->getMockBuilder(Transition::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $transition->expects($this->once())
            ->method('getEntityId')
            ->willReturn('some-entity-id');
        $this->orderRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $searchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $searchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $order->expects($this->any())
            ->method('getTransactions')
            ->willReturn(
                $orderTransactionCollection = $this->getMockBuilder(OrderTransactionCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderTransactionCollection->expects($this->any())
            ->method('first')
            ->willReturn(
                $transaction = $this->getMockBuilder(OrderTransactionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $transaction->expects($this->any())
            ->method('getPaymentMethod')
            ->willReturn(
                $paymentMethod = $this->getMockBuilder(PaymentMethodEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentMethod->expects($this->any())
            ->method('getPlugin')
            ->willReturn(
                $plugin = $this->getMockBuilder(PluginEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $plugin->expects($this->once())
            ->method('getBaseClass')
            ->willReturn(PevrPayeverIntegration::class);
        $event->expects($this->once())
            ->method('getStateName')
            ->willReturn(OrderStates::STATE_CANCELLED);
        $this->payeverTriggersHandler->expects($this->once())
            ->method('cancelTransaction');

        $this->totalsManager
            ->expects($this->once())
            ->method('getAvailableForCancelling')
            ->willReturn(1.1);
        $this->listener->onOrderStateChanged($event);
    }

    public function testOnOrderStateChangedCaseCompleted()
    {
        /** @var MockObject|StateMachineStateChangeEvent $event */
        $event = $this->getMockBuilder(StateMachineStateChangeEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getTransitionSide')
            ->willReturn(StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER);
        $event->expects($this->once())
            ->method('getTransition')
            ->willReturn(
                $transition = $this->getMockBuilder(Transition::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $transition->expects($this->once())
            ->method('getEntityId')
            ->willReturn('some-entity-id');
        $this->orderRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $searchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $searchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $order->expects($this->any())
            ->method('getTransactions')
            ->willReturn(
                $orderTransactionCollection = $this->getMockBuilder(OrderTransactionCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderTransactionCollection->expects($this->any())
            ->method('first')
            ->willReturn(
                $transaction = $this->getMockBuilder(OrderTransactionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $transaction->expects($this->any())
            ->method('getPaymentMethod')
            ->willReturn(
                $paymentMethod = $this->getMockBuilder(PaymentMethodEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentMethod->expects($this->any())
            ->method('getPlugin')
            ->willReturn(
                $plugin = $this->getMockBuilder(PluginEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $plugin->expects($this->once())
            ->method('getBaseClass')
            ->willReturn(PevrPayeverIntegration::class);
        $event->expects($this->once())
            ->method('getStateName')
            ->willReturn(OrderStates::STATE_COMPLETED);
        $this->payeverTriggersHandler->expects($this->once())
            ->method('shippingTransaction');

        $this->totalsManager
            ->expects($this->any())
            ->method('getAvailableForCapturing')
            ->willReturn(1.1);
        $this->listener->onOrderStateChanged($event);
    }

    public function testOnOrderStateChangedCaseNotPayeverPaymentMethod()
    {
        /** @var MockObject|StateMachineStateChangeEvent $event */
        $event = $this->getMockBuilder(StateMachineStateChangeEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getTransitionSide')
            ->willReturn(StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER);
        $event->expects($this->once())
            ->method('getTransition')
            ->willReturn(
                $transition = $this->getMockBuilder(Transition::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $transition->expects($this->once())
            ->method('getEntityId')
            ->willReturn('some-entity-id');
        $this->orderRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $searchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $searchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $order->expects($this->any())
            ->method('getTransactions')
            ->willReturn(
                $orderTransactionCollection = $this->getMockBuilder(OrderTransactionCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderTransactionCollection->expects($this->any())
            ->method('first')
            ->willReturn(
                $transaction = $this->getMockBuilder(OrderTransactionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $transaction->expects($this->once())
            ->method('getPaymentMethod')
            ->willreturn(null);
        $this->listener->onOrderStateChanged($event);
    }

    public function testOnOrderStateChangedCaseUnsupportedTransitionSide()
    {
        /** @var MockObject|StateMachineStateChangeEvent $event */
        $event = $this->getMockBuilder(StateMachineStateChangeEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getTransitionSide')
            ->willReturn(StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_LEAVE);
        $this->listener->onOrderStateChanged($event);
    }

    public function testOnOrderTransactionStateChange()
    {
        /** @var MockObject|StateMachineStateChangeEvent $event */
        $event = $this->getMockBuilder(StateMachineStateChangeEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getTransition')
            ->willReturn(
                $transition = $this->getMockBuilder(Transition::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $transition->expects($this->once())
            ->method('getEntityId')
            ->willReturn('some-entity-id');
        $event->expects($this->any())
            ->method('getContext')
            ->willReturn(
                $context = $this->getMockBuilder(Context::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->transactionRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $transactionSearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $transactionSearchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderTransaction->expects($this->any())
            ->method('getPaymentMethod')
            ->willReturn(
                $paymentMethod = $this->getMockBuilder(PaymentMethodEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderTransaction->expects($this->any())
            ->method('getOrder')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->orderConverter->expects($this->once())
            ->method('assembleSalesChannelContext')
            ->willReturn(
                $saleChannelContext = $this->getMockBuilder(SalesChannelContext::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $saleChannelContext->expects($this->once())
            ->method('getContext')
            ->willReturn($context);
        $orderTransaction->expects($this->any())
            ->method('getOrderId')
            ->willReturn('some-order-id');
        $this->orderRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $orderSearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderSearchResult->expects($this->once())
            ->method('first')
            ->willReturn($order);
        $order->expects($this->any())
            ->method('getTransactions')
            ->willReturn(
                $orderTransactionCollection = $this->getMockBuilder(OrderTransactionCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderTransactionCollection->expects($this->any())
            ->method('first')
            ->willReturn(
                $transaction = $this->getMockBuilder(OrderTransactionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $transaction->expects($this->any())
            ->method('getPaymentMethod')
            ->willReturn(
                $paymentMethod = $this->getMockBuilder(PaymentMethodEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentMethod->expects($this->any())
            ->method('getPlugin')
            ->willReturn(
                $plugin = $this->getMockBuilder(PluginEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $plugin->expects($this->once())
            ->method('getBaseClass')
            ->willReturn(PevrPayeverIntegration::class);

        $event->expects($this->once())
            ->method('getNextState')
            ->willReturn(
                $nextState = $this->getMockBuilder(StateMachineStateEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $nextState->expects($this->once())
            ->method('getTechnicalName')
            ->willReturn(OrderTransactionStates::STATE_PAID);
        $order->expects($this->once())
            ->method('getStateMachineState')
            ->willReturn(
                $orderState = $this->getMockBuilder(StateMachineStateEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderState->expects($this->once())
            ->method('getTechnicalName')
            ->willReturn(OrderStates::STATE_OPEN);
        $this->payeverTriggersHandler->expects($this->once())
            ->method('setOrderInProgressWhenTransactionPaid');

        $this->listener->onOrderTransactionStateChange($event);
    }
}
