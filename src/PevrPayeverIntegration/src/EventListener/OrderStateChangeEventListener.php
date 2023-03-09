<?php

/**
 * payever GmbH
 *
 * NOTICE OF LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade payever Shopware package
 * to newer versions in the future.
 *
 * @category    Payever
 * @author      payever GmbH <service@payever.de>
 * @copyright   Copyright (c) 2021 payever GmbH (http://www.payever.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Payever\PayeverPayments\EventListener;

use Payever\PayeverPayments\PevrPayeverIntegration;
use Payever\PayeverPayments\Service\Management\OrderTotalsManager;
use Payever\PayeverPayments\Service\Payment\PaymentActionService;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Cart\Exception\OrderTransactionNotFoundException;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderStateChangeEventListener implements EventSubscriberInterface
{
    /** @var PaymentActionService */
    private $payeverTriggersHandler;

    /** @var EntityRepositoryInterface */
    private $orderRepository;

    /** @var OrderTotalsManager */
    private $totalsManager;

    /** @var OrderConverter */
    private $orderConverter;

    /** @var EntityRepositoryInterface */
    private $transactionRepository;

    /**
     * @param PaymentActionService $payeverTriggersHandler
     * @param EntityRepositoryInterface $orderRepository
     * @param OrderTotalsManager $totalsManager
     * @param EntityRepositoryInterface $transactionRepository
     * @param OrderConverter $orderConverter
     */
    public function __construct(
        PaymentActionService $payeverTriggersHandler,
        EntityRepositoryInterface $orderRepository,
        OrderTotalsManager $totalsManager,
        EntityRepositoryInterface $transactionRepository,
        OrderConverter $orderConverter
    ) {
        $this->payeverTriggersHandler = $payeverTriggersHandler;
        $this->orderRepository = $orderRepository;
        $this->totalsManager = $totalsManager;
        $this->orderConverter = $orderConverter;
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'state_machine.order.state_changed' => 'onOrderStateChanged',
            'state_machine.order_transaction.state_changed' => 'onOrderTransactionStateChange',
        ];
    }

    /**
     * @param StateMachineStateChangeEvent $event
     * @throws OrderNotFoundException
     * @throws OrderTransactionNotFoundException
     */
    public function onOrderTransactionStateChange(StateMachineStateChangeEvent $event): void
    {
        $orderTransactionId = $event->getTransition()->getEntityId();

        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('paymentMethod');
        $criteria->addAssociation('order.orderCustomer');
        $criteria->addAssociation('order.transactions');

        $orderTransaction = $this->transactionRepository
            ->search($criteria, $event->getContext())
            ->first();

        if ($orderTransaction === null) {
            throw new OrderTransactionNotFoundException($orderTransactionId);
        }

        if ($orderTransaction->getPaymentMethod() === null) {
            throw new OrderTransactionNotFoundException($orderTransactionId);
        }

        if ($orderTransaction->getOrder() === null) {
            throw new OrderNotFoundException($orderTransactionId);
        }

        $context = $this->getContext($orderTransaction->getOrder(), $event->getContext());

        $order = $this->getOrderFromTransaction($orderTransaction->getOrderId(), $context);

        if (!$this->isPayeverPaymentMethod($order)) {
            return;
        }

        if ($event->getNextState()->getTechnicalName() === OrderTransactionStates::STATE_PAID) {
            if ($order->getStateMachineState()->getTechnicalName() === OrderStates::STATE_OPEN) {
                $this->payeverTriggersHandler->setOrderInProgressWhenTransactionPaid($order, $context);
            }
        }
    }

    /**
     * @param string $orderId
     * @param Context $context
     * @throws OrderNotFoundException
     * @return OrderEntity
     */
    private function getOrderFromTransaction(string $orderId, Context $context): OrderEntity
    {
        $orderCriteria = $this->getOrderCriteria($orderId);

        $order = $this->orderRepository
            ->search($orderCriteria, $context)
            ->first();

        if (!$order instanceof OrderEntity) {
            throw new OrderNotFoundException($orderId);
        }

        return $order;
    }

    /**
     * @param OrderEntity $order
     * @param Context $context
     * @return Context
     */
    private function getContext(OrderEntity $order, Context $context): Context
    {
        $context = clone $context;

        $salesChannelContext = $this->orderConverter->assembleSalesChannelContext($order, $context);

        if ($order->getRuleIds() !== null) {
            $salesChannelContext->setRuleIds($order->getRuleIds());
        }

        return $salesChannelContext->getContext();
    }

    /**
     * @param StateMachineStateChangeEvent $event
     * @throws \Exception
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function onOrderStateChanged(StateMachineStateChangeEvent $event): void
    {
        if ($event->getTransitionSide() !== StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER) {
            return;
        }

        // Prevent action when another action was performed from order detail window
        if ($this->payeverTriggersHandler->orderEventLock) {
            return;
        }

        $order = $this->getOrder($event);
        if (!$this->isPayeverPaymentMethod($order)) {
            return;
        }

        $orderTransaction = $order->getTransactions()->first();
        $orderTransaction->setOrder($order);

        switch ($event->getStateName()) {
            case OrderStates::STATE_CANCELLED:
                if ($this->totalsManager->getAvailableForCancelling($order) > 0.01) {
                    try {
                        $this->payeverTriggersHandler->cancelTransaction($orderTransaction);
                    } catch (\Exception $exception) {
                        // Silence is golden
                    }
                }

                if ($this->totalsManager->getAvailableForRefunding($order) > 0.01) {
                    try {
                        $this->payeverTriggersHandler->refundTransaction(
                            $orderTransaction,
                            $this->totalsManager->getAvailableForRefunding($order)
                        );
                    } catch (\Exception $exception) {
                        // Silence is golden
                    }
                }

                break;
            case OrderStates::STATE_COMPLETED:
                if ($this->totalsManager->getAvailableForCapturing($order) > 0.01) {
                    try {
                        $this->payeverTriggersHandler->shippingTransaction(
                            $orderTransaction,
                            $this->totalsManager->getAvailableForCapturing($order)
                        );
                    } catch (\Exception $exception) {
                        // Silence is golden
                    }
                }

                break;
        }
    }

    /**
     * Check if this event is triggered using a payever Payment Method
     *
     * @param OrderEntity $order
     * @return bool
     */
    private function isPayeverPaymentMethod(OrderEntity $order): bool
    {
        $transaction = $order->getTransactions()->first();
        if (!$transaction || !$transaction->getPaymentMethod() || !$transaction->getPaymentMethod()->getPlugin()) {
            return false;
        }
        $plugin = $transaction->getPaymentMethod()->getPlugin();

        return $plugin->getBaseClass() === PevrPayeverIntegration::class;
    }

    /**
     * Get the data we need from the order
     *
     * @param string $orderId
     * @return Criteria
     * @throws InconsistentCriteriaIdsException
     */
    private function getOrderCriteria(string $orderId): Criteria
    {
        $orderCriteria = new Criteria([$orderId]);
        $orderCriteria->addAssociation('orderCustomer.salutation');
        $orderCriteria->addAssociation('stateMachineState');
        $orderCriteria->addAssociation('transactions');
        $orderCriteria->addAssociation('transactions.paymentMethod');
        $orderCriteria->addAssociation('transactions.paymentMethod.plugin');
        $orderCriteria->addAssociation('salesChannel');

        return $orderCriteria;
    }

    /**
     * @param StateMachineStateChangeEvent $event
     * @return OrderEntity
     * @throws InconsistentCriteriaIdsException
     */
    private function getOrder(StateMachineStateChangeEvent $event): OrderEntity
    {
        $orderCriteria = $this->getOrderCriteria($event->getTransition()->getEntityId());
        /** @var OrderEntity $order */
        $order = $this->orderRepository->search($orderCriteria, $event->getContext())->first();

        return $order;
    }
}
