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

namespace Payever\PayeverPayments\Service\Payment;

use DateTimeImmutable;
use Payever\ExternalIntegration\Payments\Action\ActionDecider;
use Payever\ExternalIntegration\Core\Base\ResponseInterface;
use Payever\ExternalIntegration\Payments\Http\RequestEntity\PaymentItemEntity;
use Payever\ExternalIntegration\Payments\Http\RequestEntity\ShippingDetailsEntity;
use Payever\ExternalIntegration\Payments\Http\RequestEntity\ShippingGoodsPaymentRequest;
use Payever\ExternalIntegration\Payments\Http\ResponseEntity\ShippingGoodsPaymentResponse;
use Payever\ExternalIntegration\Payments\PaymentsApiClient;
use Payever\PayeverPayments\PevrPayeverIntegration;
use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use Payever\PayeverPayments\Service\Management\OrderTotalsManager;
use Payever\PayeverPayments\Service\Management\OrderItemsManager;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionEntity;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PaymentActionService
{
    const SMALL_AMOUNT = 0.001;

    /** @var ClientFactory */
    private $apiClientFactory;

    /** @var EntityRepositoryInterface */
    private $orderRepository;

    /** @var EntityRepositoryInterface */
    private $orderTransactionRepository;

    /** @var EntityRepositoryInterface */
    private $orderDeliveryRepository;

    /** @var EntityRepositoryInterface */
    private $stateRepository;

    /** @var ContainerInterface */
    protected $container;

    /** @var PaymentsApiClient[] */
    private $paymentsApiClients = [];

    /** @var ActionDecider[] */
    private $actionDeciders = [];

    /** @var OrderService */
    private $orderService;

    /** @var StateMachineRegistry */
    private $stateMachineRegistry;

    /** @var OrderTotalsManager */
    private $totalsManager;

    /** @var OrderItemsManager */
    private $orderItemsManager;

    /**
     * @var bool
     */
    public $orderEventLock = false;

    /**
     * @param ClientFactory $apiClientFactory
     * @param EntityRepositoryInterface $orderRepository
     * @param EntityRepositoryInterface $orderTransactionRepository
     * @param EntityRepositoryInterface $orderDeliveryRepository
     * @param EntityRepositoryInterface $stateRepository
     * @param OrderService $orderService
     * @param StateMachineRegistry $stateMachineRegistry
     * @param OrderTotalsManager $totalsManager
     * @param OrderItemsManager $orderItemsManager
     */
    public function __construct(
        ClientFactory $apiClientFactory,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderTransactionRepository,
        EntityRepositoryInterface $orderDeliveryRepository,
        EntityRepositoryInterface $stateRepository,
        OrderService $orderService,
        StateMachineRegistry $stateMachineRegistry,
        OrderTotalsManager $totalsManager,
        OrderItemsManager $orderItemsManager
    ) {
        $this->apiClientFactory = $apiClientFactory;
        $this->orderRepository = $orderRepository;
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->orderDeliveryRepository = $orderDeliveryRepository;
        $this->stateRepository = $stateRepository;
        $this->orderService = $orderService;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->totalsManager = $totalsManager;
        $this->orderItemsManager = $orderItemsManager;
    }

    /**
     * Ship goods.
     *
     * @param OrderTransactionEntity $orderTransaction
     * @param array $items
     * @throws \RuntimeException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function shipGoodsTransaction(OrderTransactionEntity $orderTransaction, array $items): void
    {
        $order = $orderTransaction->getOrder();

        try {
            $customFields = $orderTransaction->getCustomFields() ?? [];
            $paymentId = $customFields[PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_ID] ?? '';

            $actionDecider = $this->getActionDecider($orderTransaction);
            if (!$actionDecider->isShippingAllowed($paymentId, false)) {
                throw new \RuntimeException('Shipping goods is not available.');
            }

            if (!$actionDecider->isPartialShippingAllowed($paymentId, false)) {
                throw new \RuntimeException('Partial Shipping goods is not available.');
            }

            $shippingGoodsRequest = new ShippingGoodsPaymentRequest();

            // Set payment items
            $shippingGoodsRequest->setPaymentItems($this->preparePaymentItems($items));

            // Set delivery fee
            $deliveryFee = $this->prepareDeliveryFee($items);
            if ($deliveryFee) {
                $shippingGoodsRequest->setDeliveryFee($deliveryFee);
            }

            // Add shipping details
            $shippingGoodsRequest->setShippingDetails($this->getShippingDetails($order));

            /** @var ResponseInterface|ShippingGoodsPaymentResponse $response */
            $response = $this->getPaymentsApiClient($orderTransaction)
                             ->shippingGoodsPaymentRequest($paymentId, $shippingGoodsRequest);

            if ($response) {
                // Save shipped qty
                $calculated = 0;
                foreach ($items as $itemId => $qty) {
                    $orderItem = $this->orderItemsManager->getItem($itemId);
                    $calculated += ($orderItem->getUnitPrice() * $qty);

                    $this->orderItemsManager->addCaptured($itemId, $qty);
                }

                // Save shipped amount
                $this->totalsManager->addCaptured($order, $calculated);

                // Change Delivery status to Shipped
                $this->markOrderShipped($this->getOrder($orderTransaction->getOrderId()));
            }
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Shipping request failed: %s', $exception->getMessage()));
        }
    }

    /**
     * Refund Items.
     *
     * @param OrderTransactionEntity $orderTransaction
     * @param array $items
     * @throws \RuntimeException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function refundItemTransaction(OrderTransactionEntity $orderTransaction, array $items): void
    {
        $order = $orderTransaction->getOrder();

        try {
            $customFields = $orderTransaction->getCustomFields() ?? [];
            $paymentId = $customFields[PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_ID] ?? '';

            if (!$this->getActionDecider($orderTransaction)->isPartialRefundAllowed($paymentId, false)) {
                throw new \RuntimeException('Partial Refund action is not available.');
            }

            /** @var ResponseInterface $response */
            $response = $this->getPaymentsApiClient($orderTransaction)
                             ->refundItemsPaymentRequest(
                                 $paymentId,
                                 $this->preparePaymentItems($items),
                                 $this->prepareDeliveryFee($items)
                             );

            if ($response) {
                // Save refunded qty
                $calculated = 0;
                foreach ($items as $itemId => $qty) {
                    $orderItem = $this->orderItemsManager->getItem($itemId);
                    $calculated += $orderItem->getUnitPrice() * $qty;

                    $this->orderItemsManager->addRefunded($itemId, $qty);

                    // Restock item
                    $this->orderItemsManager->restockOrderItem($itemId, $qty);
                }

                // Save refunded amount
                $this->totalsManager->addRefunded($order, $calculated);

                // Change Payment status to Refunded
                $this->markOrderRefunded($this->getOrder($orderTransaction->getOrderId()));
            }
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Items Refund request failed: %s', $exception->getMessage()));
        }
    }

    /**
     * Cancel Items.
     *
     * @param OrderTransactionEntity $orderTransaction
     * @param array $items
     * @throws \RuntimeException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function cancelItemTransaction(OrderTransactionEntity $orderTransaction, array $items): void
    {
        $order = $orderTransaction->getOrder();

        try {
            $customFields = $orderTransaction->getCustomFields() ?? [];
            $paymentId = $customFields[PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_ID] ?? '';

            if (!$this->getActionDecider($orderTransaction)->isPartialCancelAllowed($paymentId, false)) {
                throw new \RuntimeException('Partial Cancel action is not available.');
            }

            /** @var ResponseInterface $response */
            $response = $this->getPaymentsApiClient($orderTransaction)
                ->cancelItemsPaymentRequest(
                    $paymentId,
                    $this->preparePaymentItems($items),
                    $this->prepareDeliveryFee($items)
                );

            if ($response) {
                // Save cancelled qty
                $calculated = 0;
                foreach ($items as $itemId => $qty) {
                    $orderItem = $this->orderItemsManager->getItem($itemId);
                    $calculated += $orderItem->getUnitPrice() * $qty;

                    $this->orderItemsManager->addCancelled($itemId, $qty);
                }

                // Save refunded amount
                $this->totalsManager->addCancelled($order, $calculated);

                // Change Payment status to Refunded
                $this->markOrderCancelled($this->getOrder($orderTransaction->getOrderId()));
            }
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Items Cancel request failed: %s', $exception->getMessage()));
        }
    }

    /**
     * Ship Amount.
     * @param float $amount
     * @param bool $isManual
     *
     * @param OrderTransactionEntity $orderTransaction
     * @throws \RuntimeException
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function shippingTransaction(
        OrderTransactionEntity $orderTransaction,
        float $amount,
        bool $isManual = false
    ): void {
        $order = $orderTransaction->getOrder();

        try {
            $customFields = $orderTransaction->getCustomFields() ?? [];
            $paymentId = $customFields[PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_ID] ?? '';
            if (!$this->getActionDecider($orderTransaction)->isShippingAllowed($paymentId, false)) {
                throw new \RuntimeException('Shipping goods is not available.');
            }

            if ($amount < self::SMALL_AMOUNT) {
                $amount = $this->totalsManager->getAvailableForCapturing($order);
            }

            $shippingGoodsRequest = new ShippingGoodsPaymentRequest();
            $shippingGoodsRequest->setAmount($amount);

            // Add shipping details
            $shippingGoodsRequest->setShippingDetails($this->getShippingDetails($order));

            $response = $this->getPaymentsApiClient($orderTransaction)
                 ->shippingGoodsPaymentRequest($paymentId, $shippingGoodsRequest);

            if ($response) {
                // Save captured amount
                $this->totalsManager->addCaptured($order, $amount, $isManual);
            }

            // Change Delivery status to Shipped
            $this->markOrderShipped($this->getOrder($orderTransaction->getOrderId()));
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Shipping goods action failed: %s', $exception->getMessage()));
        }
    }

    /**
     * Refund Amount.
     *
     * @param OrderTransactionEntity $orderTransaction
     * @param float $amount
     * @param bool $isManual
     * @throws \RuntimeException
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function refundTransaction(
        OrderTransactionEntity $orderTransaction,
        float $amount,
        bool $isManual = false
    ): void {
        $order = $orderTransaction->getOrder();

        try {
            $customFields = $orderTransaction->getCustomFields() ?? [];
            $paymentId = $customFields[PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_ID] ?? '';

            if (!$this->getActionDecider($orderTransaction)->isRefundAllowed($paymentId, false)) {
                throw new \RuntimeException('Refund action is not available.');
            }

            if ($order->getAmountTotal() < $amount) {
                throw new \Exception('Refund amount more than total amount.');
            }

            if ($amount > $this->totalsManager->getAvailableForRefunding($order)) {
                throw new \Exception('Refund amount more than available amount.');
            }

            if ($amount < self::SMALL_AMOUNT) {
                $amount = $this->totalsManager->getAvailableForRefunding($order);
            }

            $response = $this->getPaymentsApiClient($orderTransaction)
                ->refundPaymentRequest($paymentId, $amount);

            if ($response) {
                $this->totalsManager->addRefunded($order, $amount, $isManual);

                // Change Payment status to Refunded
                $this->markOrderRefunded($this->getOrder($orderTransaction->getOrderId()));
            }
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Refund request failed: %s', $exception->getMessage()));
        }
    }

    /**
     * Cancel amount.
     *
     * @param OrderTransactionEntity $orderTransaction
     * @param float|null $amount
     * @param bool $isManual
     * @SuppressWarnings(PHPMD.ElseExpression)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function cancelTransaction(
        OrderTransactionEntity $orderTransaction,
        ?float $amount = null,
        bool $isManual = false
    ): void {
        $order = $orderTransaction->getOrder();

        try {
            $customFields = $orderTransaction->getCustomFields() ?? [];
            $paymentId = $customFields[PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_ID] ?? '';

            $actionDecider = $this->getActionDecider($orderTransaction);
            if (!$actionDecider->isCancelAllowed($paymentId, false)) {
                throw new \RuntimeException('Cancel action is not available.');
            }

            if ($amount) {
                // Partial cancellation
                if (!$actionDecider->isPartialCancelAllowed($paymentId, false)) {
                    throw new \Exception('Partial Cancel action is not available.');
                }

                if ($order->getAmountTotal() < $amount) {
                    throw new \Exception('Cancel amount more than total amount.');
                }

                if ($amount > $this->totalsManager->getAvailableForCancelling($order)) {
                    throw new \Exception('Cancel amount more than available amount.');
                }

                if ($amount < self::SMALL_AMOUNT) {
                    $amount = $this->totalsManager->getAvailableForCancelling($order);
                }

                $response = $this->getPaymentsApiClient($orderTransaction)
                     ->cancelPaymentRequest($paymentId, $amount);
            } else {
                $response = $this->getPaymentsApiClient($orderTransaction)
                     ->cancelPaymentRequest($paymentId);
            }

            if ($response) {
                // Save cancelled amount
                $this->totalsManager->addCancelled($order, $amount ? $amount : $order->getAmountTotal(), $isManual);

                $this->markOrderCancelled($this->getOrder($orderTransaction->getOrderId()));
            }
        } catch (\Exception $exception) {
            throw new \RuntimeException(sprintf('Cancel failed: %s', $exception->getMessage()));
        }
    }

    /**
     * Mark Order Shipped.
     *
     * @param OrderEntity $order
     *
     * @return void
     * @SuppressWarnings(PHPMD.ElseExpression)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    private function markOrderShipped(OrderEntity $order): void
    {
        if (defined('Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions::ACTION_PAID')) { //phpcs:ignore
            $paymentState = StateMachineTransitionActions::ACTION_PAID;
            $deliveryState = StateMachineTransitionActions::ACTION_SHIP;
            if ($this->totalsManager->getAvailableForCapturing($order) >= self::SMALL_AMOUNT) {
                $deliveryState = StateMachineTransitionActions::ACTION_SHIP_PARTIALLY;
                $paymentState = StateMachineTransitionActions::ACTION_PAID_PARTIALLY;
            }
        } else {
            $paymentState = 'paid';
            $deliveryState = StateMachineTransitionActions::ACTION_SHIP;
            if ($this->totalsManager->getAvailableForCapturing($order) >= self::SMALL_AMOUNT) {
                $deliveryState = StateMachineTransitionActions::ACTION_SHIP_PARTIALLY;
                $paymentState = 'paid_partially';
            }
        }

        // Change Order status
        if (
            !$this->orderStateTransition(
                $order->getId(),
                StateMachineTransitionActions::ACTION_PROCESS,
                new RequestDataBag(),
                Context::createDefaultContext()
            )
        ) {
            $data = [
                'id' => $order->getId(),
                'stateId' => $this->getPaymentState('in_progress')->getId(),
            ];
            $this->orderRepository->update([$data], Context::createDefaultContext());
        }

        // Change Payment status
        $transactions = $order->getTransactions();
        if ($transactions && $transactions->count() > 0) {
            /** @var OrderTransactionEntity $transaction */
            $transaction = $transactions->first();

            if (
                !$this->orderTransactionStateTransition(
                    $transaction->getId(),
                    $paymentState,
                    new RequestDataBag(),
                    Context::createDefaultContext()
                )
            ) {
                $data = [
                    'id' => $transaction->getId(),
                    'stateId' => $this->getPaymentState($paymentState)->getId(),
                ];
                $this->orderTransactionRepository->update([$data], Context::createDefaultContext());
            }
        }

        // Change Delivery status to Shipped
        $delivery = $order->getDeliveries();
        if ($delivery && $delivery->count() > 0) {
            $delivery = $delivery->first();

            if (
                !$this->orderDeliveryStateTransition(
                    $delivery->getId(),
                    $deliveryState,
                    new RequestDataBag(),
                    Context::createDefaultContext()
                )
            ) {
                $deliveryState = 'shipped';
                if ($this->totalsManager->getAvailableForCapturing($order) >= self::SMALL_AMOUNT) {
                    $deliveryState = 'shipped_partially';
                }

                $data = [
                    'id' => $delivery->getId(),
                    'stateId' => $this->getPaymentState($deliveryState)->getId(),
                ];
                $this->orderDeliveryRepository->update([$data], Context::createDefaultContext());
            }
        }
    }

    /**
     * Mark Order Refunded.
     *
     * @param OrderEntity $order
     *
     * @return void
     * @SuppressWarnings(PHPMD.ElseExpression)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function markOrderRefunded(OrderEntity $order): void
    {
        // Check if fully refunded and change payment status
        $paymentState = StateMachineTransitionActions::ACTION_REFUND;
        $deliveryState = StateMachineTransitionActions::ACTION_RETOUR;
        $availableAmount = $this->totalsManager->getAvailableForRefunding($order);
        if ($availableAmount >= self::SMALL_AMOUNT) {
            $paymentState = StateMachineTransitionActions::ACTION_REFUND_PARTIALLY;
            $deliveryState = StateMachineTransitionActions::ACTION_RETOUR_PARTIALLY;
        }

        // Change Order status
        if ($paymentState === StateMachineTransitionActions::ACTION_REFUND) {
            if (
                !$this->orderStateTransition(
                    $order->getId(),
                    StateMachineTransitionActions::ACTION_CANCEL,
                    new RequestDataBag(),
                    Context::createDefaultContext()
                )
            ) {
                $data = [
                    'id' => $order->getId(),
                    'stateId' => $this->getPaymentState(
                        'cancelled'
                    )->getId(),
                ];
                $this->orderRepository->update([$data], Context::createDefaultContext());
            }
        }

        // Change Payment status
        $transactions = $order->getTransactions();
        if ($transactions && $transactions->count() > 0) {
            /** @var OrderTransactionEntity $transaction */
            $transaction = $transactions->first();

            if (
                !$this->orderTransactionStateTransition(
                    $transaction->getId(),
                    $paymentState,
                    new RequestDataBag(),
                    Context::createDefaultContext()
                )
            ) {
                $paymentState = 'refunded';
                if ($availableAmount >= self::SMALL_AMOUNT) {
                    $paymentState = 'refunded_partially';
                }
                $data = [
                    'id' => $transaction->getId(),
                    'stateId' => $this->getPaymentState($paymentState)->getId(),
                ];
                $this->orderTransactionRepository->update([$data], Context::createDefaultContext());
            }
        }

        // Change Delivery status to Returned
        $delivery = $order->getDeliveries();
        if ($delivery && $delivery->count() > 0) {
            $delivery = $delivery->first();

            if (
                !$this->orderDeliveryStateTransition(
                    $delivery->getId(),
                    $deliveryState,
                    new RequestDataBag(),
                    Context::createDefaultContext()
                )
            ) {
                $deliveryState = 'returned';
                if ($availableAmount >= self::SMALL_AMOUNT) {
                    $deliveryState = 'returned_partially';
                }

                $data = [
                    'id' => $delivery->getId(),
                    'stateId' => $this->getPaymentState($deliveryState)->getId(),
                ];
                $this->orderDeliveryRepository->update([$data], Context::createDefaultContext());
            }
        }
    }

    /**
     * Mark Order cancelled.
     *
     * @param OrderEntity $order
     *
     * @return void
     * @SuppressWarnings(PHPMD.ElseExpression)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function markOrderCancelled(OrderEntity $order): void
    {
        // Mark as shipped if there's no available amount for capturing
        if (
            $this->totalsManager->getCancelled($order) > 0 &&
            $this->totalsManager->getAvailableForCapturing($order) < self::SMALL_AMOUNT
        ) {
            $this->markOrderShipped($order);
            return;
        }

        // Don't mark order cancelled if have captured amount
        if ($this->totalsManager->getCaptured($order) > 0) {
            return;
        }

        // Check if fully cancelled and change payment status
        $availableAmount = $this->totalsManager->getAvailableForCancelling($order);
        if ($availableAmount < self::SMALL_AMOUNT) {
            // Change order status
            if (
                !$this->orderStateTransition(
                    $order->getId(),
                    StateMachineTransitionActions::ACTION_CANCEL,
                    new RequestDataBag(),
                    Context::createDefaultContext()
                )
            ) {
                $data = [
                    'id' => $order->getId(),
                    'stateId' => $this->getPaymentState(
                        'cancelled'
                    )->getId(),
                ];
                $this->orderRepository->update([$data], Context::createDefaultContext());
            }

            // Change Payment status
            $transactions = $order->getTransactions();
            if ($transactions && $transactions->count() > 0) {
                /** @var OrderTransactionEntity $transaction */
                $transaction = $transactions->first();

                if (
                    !$this->orderTransactionStateTransition(
                        $transaction->getId(),
                        StateMachineTransitionActions::ACTION_CANCEL,
                        new RequestDataBag(),
                        Context::createDefaultContext()
                    )
                ) {
                    $data = [
                        'id' => $transaction->getId(),
                        'stateId' => $this->getPaymentState('cancelled')->getId(),
                    ];
                    $this->orderTransactionRepository->update([$data], Context::createDefaultContext());
                }
            }

            // Change Delivery status to Cancelled
            $delivery = $order->getDeliveries();
            if ($delivery && $delivery->count() > 0) {
                $delivery = $delivery->first();

                if (
                    !$this->orderDeliveryStateTransition(
                        $delivery->getId(),
                        StateMachineTransitionActions::ACTION_CANCEL,
                        new RequestDataBag(),
                        Context::createDefaultContext()
                    )
                ) {
                    $data = [
                        'id' => $delivery->getId(),
                        'stateId' => $this->getPaymentState(
                            'cancelled'
                        )->getId(),
                    ];
                    $this->orderDeliveryRepository->update([$data], Context::createDefaultContext());
                }
            }
        }
    }

    /**
     * Prepare Payment items.
     * It returns product type only.
     *
     * @param array $items
     *
     * @return PaymentItemEntity[]
     */
    private function preparePaymentItems(array $items): array
    {
        $paymentItems = [];
        foreach ($items as $itemId => $qty) {
            $orderItem = $this->orderItemsManager->getItem($itemId);

            if ($orderItem->getItemType() !== OrderItemsManager::TYPE_SHIPPING) {
                $paymentEntity = new PaymentItemEntity();
                $paymentEntity->setIdentifier($orderItem->getIdentifier())
                              ->setName($orderItem->getLabel())
                              ->setPrice($orderItem->getUnitPrice())
                              ->setQuantity($qty);

                $paymentItems[] = $paymentEntity;
            }
        }

        return $paymentItems;
    }

    /**
     * Get Delivery Fee.
     *
     * @param array $items
     *
     * @return float|null
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    private function prepareDeliveryFee(array $items): ?float
    {
        foreach ($items as $itemId => $qty) {
            $orderItem = $this->orderItemsManager->getItem($itemId);

            switch ($orderItem->getItemType()) {
                case OrderItemsManager::TYPE_SHIPPING:
                    return $orderItem->getUnitPrice();
                default:
                    // no break
            }
        }

        return null;
    }

    /**
     * Get Shipping Details Entity.
     *
     * @param OrderEntity $order
     *
     * @return ShippingDetailsEntity
     */
    private function getShippingDetails(OrderEntity $order): ShippingDetailsEntity
    {
        $shippingDetails = new ShippingDetailsEntity();

        // Add shipping details
        $delivery = $order->getDeliveries();
        if ($delivery && $delivery->count() > 0) {
            $delivery = $delivery->first();

            /** @var OrderDeliveryEntity $delivery */
            $tracks = (array) $delivery->getTrackingCodes();
            $shippingMethod = $delivery->getShippingMethod();
            $shippingName = $shippingMethod->getName();

            // Get tracking url
            $trackingUrl = null;
            if (method_exists($shippingMethod, 'getTrackingUrl')) {
                $trackingUrl = $shippingMethod->getTrackingUrl();
            }

            /** @var DateTimeImmutable $shippingDate */
            $shippingDate = $delivery->getUpdatedAt();
            if (!$shippingDate) {
                $shippingDate = $delivery->getCreatedAt();
            }

            if ($shippingDate instanceof DateTimeImmutable) {
                $shippingDate = $shippingDate->format('Y-m-d');
            }

            $shippingDetails
                ->setReturnCarrier($shippingName)
                ->setReturnTrackingNumber(implode(',', $tracks))
                ->setReturnTrackingUrl($trackingUrl)
                ->setShippingCarrier($shippingName)
                ->setShippingDate($shippingDate)
                ->setShippingMethod($shippingName)
                ->setTrackingNumber(implode(',', $tracks))
                ->setTrackingUrl($trackingUrl);
        }

        return $shippingDetails;
    }

    /**
     * @param string $state
     * @return StateMachineStateEntity
     */
    private function getPaymentState(string $state): StateMachineStateEntity
    {
        $criteria = new Criteria();
        $filter = new EqualsFilter('state_machine_state.technicalName', $state);
        $criteria->addFilter($filter);
        $paymentState = $this->stateRepository->search($criteria, Context::createDefaultContext())->first();
        if (!$paymentState) {
            throw new \RuntimeException('Payment state is not found: ' . $state);
        }

        return $paymentState;
    }

    /**
     * @param OrderTransactionEntity $orderTransaction
     * @return ActionDecider
     * @throws \Exception
     */
    private function getActionDecider(OrderTransactionEntity $orderTransaction): ActionDecider
    {
        $apiClient = $this->getPaymentsApiClient($orderTransaction);
        $objectHash = spl_object_hash($apiClient);
        if (empty($this->actionDeciders[$objectHash])) {
            $this->actionDeciders[$objectHash] = new ActionDecider($apiClient);
        }

        return $this->actionDeciders[$objectHash];
    }

    /**
     * @param OrderTransactionEntity $orderTransaction
     * @return PaymentsApiClient
     * @throws \Exception
     */
    private function getPaymentsApiClient(OrderTransactionEntity $orderTransaction): PaymentsApiClient
    {
        $order = $this->assertOrder($orderTransaction);
        $salesChannelId = $order->getSalesChannelId();
        if (empty($this->paymentsApiClients[$salesChannelId])) {
            $this->paymentsApiClients[$salesChannelId] = $this->apiClientFactory
                ->getPaymentsApiClient($salesChannelId);
        }

        return $this->paymentsApiClients[$salesChannelId];
    }

    /**
     * @param OrderTransactionEntity $orderTransaction
     * @return \Shopware\Core\Checkout\Order\OrderEntity
     */
    private function assertOrder(OrderTransactionEntity $orderTransaction)
    {
        $order = $orderTransaction->getOrder();
        if (!$order) {
            throw new \RuntimeException('Order transaction does not have order entity');
        }

        return $order;
    }

    /**
     * Get Order.
     *
     * @param string $orderId
     *
     * @return OrderEntity
     */
    private function getOrder(string $orderId)
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('id', $orderId)
        );
        $criteria->addAssociation('deliveries');
        $criteria->addAssociation('transactions');

        /** @var OrderEntity[] $entities */
        return $this->orderRepository
            ->search($criteria, Context::createDefaultContext())
            ->first();
    }

    /**
     * @param string $stateMachineName
     * @param string $fromStateId
     * @param Context $context
     *
     * @return StateMachineTransitionEntity[]
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     */
    private function getAvailableTransitionsById(string $stateMachineName, string $fromStateId, Context $context): array
    {
        $stateMachine = $this->stateMachineRegistry->getStateMachine($stateMachineName, $context);

        $transitions = [];
        foreach ($stateMachine->getTransitions() as $transition) {
            if ($transition->getFromStateMachineState()->getId() === $fromStateId) {
                $transitions[] = $transition;
            }
        }

        return $transitions;
    }

    /**
     * set order in-progress when order transaction paid.
     * @param OrderEntity $order
     * @param Context $context
     */
    public function setOrderInProgressWhenTransactionPaid(OrderEntity $order, Context $context): void
    {
        if (
            !$this->stateMachineRegistry->transition(new Transition(
                OrderDefinition::ENTITY_NAME,
                $order->getId(),
                StateMachineTransitionActions::ACTION_PROCESS,
                'stateId'
            ), $context)
        ) {
            $data = [
                'id' => $order->getId(),
                'stateId' => OrderStates::STATE_IN_PROGRESS,
            ];
            $this->orderRepository->update([$data], $context);
        }
    }

    /**
     * Change order state transition.
     *
     * @param string $entityId
     * @param string $transition
     * @param ParameterBag $data
     * @param Context $context
     * @return bool
     */
    private function orderStateTransition(
        string $entityId,
        string $transition,
        ParameterBag $data,
        Context $context
    ): bool {
        if (!method_exists($this->orderService, 'orderStateTransition')) {
            return false;
        }

        try {
            $this->orderService->orderStateTransition(
                $entityId,
                $transition,
                $data,
                $context
            );

            return true;
        } catch (IllegalTransitionException $exception) {
            return false;
        }
    }

    /**
     * Change order transaction state transition.
     *
     * @param string $entityId
     * @param string $transition
     * @param ParameterBag $data
     * @param Context $context
     * @return bool
     */
    private function orderTransactionStateTransition(
        string $entityId,
        string $transition,
        ParameterBag $data,
        Context $context
    ): bool {
        if (!method_exists($this->orderService, 'orderTransactionStateTransition')) {
            return false;
        }

        try {
            $this->orderService->orderTransactionStateTransition(
                $entityId,
                $transition,
                $data,
                $context
            );

            return true;
        } catch (IllegalTransitionException $exception) {
            return false;
        }
    }

    /**
     * Change order delivery state transition.
     *
     * @param string $entityId
     * @param string $transition
     * @param ParameterBag $data
     * @param Context $context
     * @return bool
     */
    private function orderDeliveryStateTransition(
        string $entityId,
        string $transition,
        ParameterBag $data,
        Context $context
    ): bool {
        if (!method_exists($this->orderService, 'orderDeliveryStateTransition')) {
            return false;
        }

        try {
            $this->orderService->orderDeliveryStateTransition(
                $entityId,
                $transition,
                $data,
                $context
            );

            return true;
        } catch (IllegalTransitionException $exception) {
            return false;
        }
    }
}
