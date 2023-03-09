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

use Payever\ExternalIntegration\Payments\Enum\Status;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\PayeverPayments\PevrPayeverIntegration;
use Payever\PayeverPayments\Service\Management\OrderTotalsManager;
use Payever\PayeverPayments\Service\Management\OrderItemsManager;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class TransactionStatusService
{
    private const STATE_AUTHORIZED = 'authorize';
    private const STATE_PAID = 'paid';
    private const STATE_IN_PROGRESS = 'in_progress';

    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionRepository;

    /**
     * @var OrderTransactionStateHandler
     */
    private $orderTransactionStateHandler;

    /** @var EntityRepositoryInterface */
    private $stateRepository;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /** @var OrderTotalsManager */
    private $totalsManager;

    /** @var OrderItemsManager */
    private $orderItemsManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $shopwareKernelVersion;

    /**
     * @param EntityRepositoryInterface $orderTransactionRepository
     * @param OrderTransactionStateHandler $orderTransactionStateHandler
     * @param StateMachineRegistry $stateMachineRegistry
     * @param EntityRepositoryInterface $orderRepository
     * @param OrderTotalsManager $totalsManager
     * @param OrderItemsManager $orderItemsManager
     * @param LoggerInterface $logger
     * @param string $shopwareKernelVersion
     */
    public function __construct(
        EntityRepositoryInterface $orderTransactionRepository,
        OrderTransactionStateHandler $orderTransactionStateHandler,
        EntityRepositoryInterface $stateRepository,
        StateMachineRegistry $stateMachineRegistry,
        EntityRepositoryInterface $orderRepository,
        OrderTotalsManager $totalsManager,
        OrderItemsManager $orderItemsManager,
        LoggerInterface $logger,
        string $shopwareKernelVersion
    ) {
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->orderTransactionStateHandler = $orderTransactionStateHandler;
        $this->stateRepository = $stateRepository;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->orderRepository = $orderRepository;
        $this->totalsManager = $totalsManager;
        $this->orderItemsManager = $orderItemsManager;
        $this->logger = $logger;
        $this->shopwareKernelVersion = $shopwareKernelVersion;
    }

    /**
     * @param SalesChannelContext $salesChannelContext
     * @param RetrievePaymentResultEntity $payeverPayment
     * @param bool $updateTransactionState
     *
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     * @throws \Shopware\Core\System\StateMachine\Exception\IllegalTransitionException
     * @throws \Shopware\Core\System\StateMachine\Exception\StateMachineInvalidEntityIdException
     * @throws \Shopware\Core\System\StateMachine\Exception\StateMachineInvalidStateFieldException
     * @throws \Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function persistTransactionStatus(
        SalesChannelContext $salesChannelContext,
        RetrievePaymentResultEntity $payeverPayment,
        bool $updateTransactionState = true
    ): void {
        $context = $salesChannelContext->getContext();
        $orderTransaction = $this->getOrderTransactionByReference(
            $context,
            $payeverPayment->getReference()
        );

        $customFields = $orderTransaction->getCustomFields() ?? [];
        $customFields[PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_ID] = $payeverPayment->getId();
        $customFields[PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_AMOUNT] = $payeverPayment->getTotal();
        $usageText = $payeverPayment->getPaymentDetails()->getUsageText();
        if ($usageText) {
            $customFields[PevrPayeverIntegration::CUSTOM_FIELD_PAN_ID] = $usageText;
        }

        $this->updateTransactionCustomFields($orderTransaction->getId(), $customFields);
        if ($updateTransactionState) {
            $this->transitionTransactionState(
                $orderTransaction->getId(),
                $payeverPayment,
                $context
            );
        }
    }

    /**
     * @param string $reference
     * @param int $notificationTimestamp
     * @param SalesChannelContext $salesChannelContext
     * @return bool
     */
    public function shouldRejectNotification(
        string $reference,
        int $notificationTimestamp,
        SalesChannelContext $salesChannelContext
    ): bool {
        $orderTransaction = $this->getOrderTransactionByReference(
            $salesChannelContext->getContext(),
            $reference
        );

        $customFields = $orderTransaction->getCustomFields() ?? [];
        $lastTimestamp = $customFields[PevrPayeverIntegration::CUSTOM_FIELD_NOTIFICATION_TIMESTAMP] ?? 0;

        return ($lastTimestamp > $notificationTimestamp);
    }

    /**
     * @param string $reference
     * @param int $notificationTime
     * @param SalesChannelContext $salesChannelContext
     */
    public function updateNotificationTimestamp(
        string $reference,
        int $notificationTime,
        SalesChannelContext $salesChannelContext
    ): void {
        $orderTransaction = $this->getOrderTransactionByReference(
            $salesChannelContext->getContext(),
            $reference
        );

        $customFields = $orderTransaction->getCustomFields() ?? [];
        $customFields[PevrPayeverIntegration::CUSTOM_FIELD_NOTIFICATION_TIMESTAMP] = $notificationTime;

        $this->updateTransactionCustomFields($orderTransaction->getId(), $customFields);
    }

    /**
     * @param string $orderTransactionId
     * @param array $customFields
     */
    public function updateTransactionCustomFields(string $orderTransactionId, array $customFields): void
    {
        $data = [
            'id'           => $orderTransactionId,
            'customFields' => $customFields,
        ];

        $this->orderTransactionRepository->update([$data], Context::createDefaultContext());
    }

    /**
     * @param Context $context
     * @param string $orderId
     * @return OrderTransactionEntity|null
     */
    public function getOrderTransactionById(Context $context, string $orderId): ?OrderTransactionEntity
    {
        $criteria = new Criteria();
        $filter = new EqualsFilter('order_transaction.id', $orderId);
        $criteria->addFilter($filter);

        return $this->orderTransactionRepository->search($criteria, $context)->first();
    }

    /**
     * @param Context $context
     * @param string $transactionId
     */
    public function cancelOrderTransaction(Context $context, string $transactionId): void
    {
        $this->orderTransactionStateHandler->cancel($transactionId, $context);
    }

    /**
     * @param array $paymentOptions
     * @param Context $context
     * @return EntitySearchResult
     */
    public function getNotFinishedTransactions(array $paymentOptions, Context $context): EntitySearchResult
    {
        $initialStateId = $this->stateMachineRegistry
            ->getInitialState(OrderTransactionStates::STATE_MACHINE, $context)
            ->getId();
        $dateTime = (new \DateTime())->add(\DateInterval::createFromDateString('-8 hours'));
        $criteria = new Criteria();
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsAnyFilter('order_transaction.paymentMethodId', $paymentOptions),
                new EqualsFilter('order_transaction.stateId', $initialStateId),
                new RangeFilter(
                    'createdAt',
                    [
                        RangeFilter::LTE => $dateTime->format(DATE_ATOM),
                    ]
                )
            ])
        );

        return $this->orderTransactionRepository->search($criteria, $context);
    }

    /**
     * @param string $payeverStatus
     * @return bool
     */
    public function isSuccessfulPaymentStatus(string $payeverStatus): bool
    {
        return in_array(
            $payeverStatus,
            [
                Status::STATUS_IN_PROCESS,
                Status::STATUS_ACCEPTED,
                Status::STATUS_PAID,
            ],
            true
        );
    }

    /**
     * Allocate Order Totals.
     *
     * @param $orderId
     * @param RetrievePaymentResultEntity $payeverPayment
     * @return void
     * @throws \Exception
     */
    public function allocateOrderTotals($orderId, RetrievePaymentResultEntity $payeverPayment)
    {
        $order = $this->getOrder($orderId);
        if (!$order) {
            throw new \Exception('Order is not found: ' . $orderId);
        }

        if (!$this->isSuccessfulPaymentStatus($payeverPayment->getStatus())) {
            throw new \Exception('Unable to allocate totals of failed payment.');
        }

        // Create order items in totals table
        $this->orderItemsManager->createItemsEntities($orderId);
        $this->totalsManager->addCaptured($order, 0);

        // Add capturing totals
        if (
            $payeverPayment->getStatus() === Status::STATUS_PAID &&
            $this->totalsManager->getCaptured($order) <= 0.001
        ) {
            $this->totalsManager->addCaptured(
                $order,
                $order->getAmountTotal()
            );
        }

        // Add capturing items
        if ($payeverPayment->getStatus() === Status::STATUS_PAID) {
            $orderItems = $this->orderItemsManager->getItems($orderId);
            foreach ($orderItems as $orderItem) {
                // Add capturing totals
                if ($orderItem['qty_captured'] === 0) {
                    $this->orderItemsManager->addCaptured($orderItem['id'], $orderItem['quantity']);
                }
            }
        }
    }

    /**
     * @param string $transactionId
     * @param RetrievePaymentResultEntity $payeverPayment
     * @param Context $context
     *
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     * @throws IllegalTransitionException
     * @throws \Shopware\Core\System\StateMachine\Exception\StateMachineInvalidEntityIdException
     * @throws \Shopware\Core\System\StateMachine\Exception\StateMachineInvalidStateFieldException
     * @throws \Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    private function transitionTransactionState(
        string $transactionId,
        RetrievePaymentResultEntity $payeverPayment,
        Context $context
    ): void {
        $orderTransaction = $this->getOrderTransactionById(
            $context,
            $transactionId
        );

        $orderState = $orderTransaction->getStateMachineState();

        $actionAuthorize = self::STATE_AUTHORIZED;
        if (defined('Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions::ACTION_AUTHORIZE')) { //phpcs:ignore
            $actionAuthorize = StateMachineTransitionActions::ACTION_AUTHORIZE;
        }

        $actionPaid = self::STATE_PAID;
        if (defined('Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions::ACTION_PAID')) { //phpcs:ignore
            $actionPaid = StateMachineTransitionActions::ACTION_PAID;
        }

        switch ($payeverPayment->getStatus()) {
            case Status::STATUS_NEW:
                break;
            case Status::STATUS_IN_PROCESS:
                if (
                    $orderState !== null
                    && in_array($orderState->getTechnicalName(), [
                        self::STATE_IN_PROGRESS,
                        self::STATE_PAID
                    ])
                ) {
                    break;
                }

                try {
                    if (method_exists($this->orderTransactionStateHandler, 'process')) {
                        $this->logger->info('Order marked in process', [$transactionId]);
                        $this->orderTransactionStateHandler->process($transactionId, $context);
                    }
                } catch (IllegalTransitionException $exception) {
                    $this->logger->critical($exception->getMessage() . ' ' . $exception->getTraceAsString());
                }

                break;
            case Status::STATUS_ACCEPTED:
                if (
                    $orderState !== null
                    && in_array($orderState->getTechnicalName(), [
                        $actionAuthorize,
                        $actionPaid
                    ])
                ) {
                    break;
                }

                if (version_compare($this->shopwareKernelVersion, '6.4.15', '>=')) {
                    // Shopware v6.4.15 supports `authorize` order states.
                    $this->authorize($transactionId, $context);
                } else {
                    $this->paid($transactionId, $context);
                }

                break;
            case Status::STATUS_PAID:
                if (
                    $orderState !== null
                    && $orderState->getTechnicalName() === $actionPaid
                ) {
                    break;
                }

                $this->paid($transactionId, $context);

                break;
            case Status::STATUS_REFUNDED:
                $this->refund($transactionId, $context);

                break;
            case Status::STATUS_CANCELLED:
            case Status::STATUS_DECLINED:
            case Status::STATUS_FAILED:
                $this->cancel($transactionId, $context);

                break;
            default:
                throw new \RuntimeException(
                    sprintf(
                        'Unknown payever payment status %s for transaction %s',
                        $payeverPayment->getStatus(),
                        $transactionId
                    )
                );
        }
    }

    /**
     * @param Context $context
     * @param string $reference
     *
     * @return OrderTransactionEntity
     */
    private function getOrderTransactionByReference(Context $context, string $reference): OrderTransactionEntity
    {
        $orderTransaction = $this->getOrderTransactionByNumber($context, $reference);

        if ($orderTransaction instanceof OrderTransactionEntity) {
            return $orderTransaction;
        }

        return $this->getOrderTransactionById($context, $reference);
    }

    /**
     * @param Context $context
     * @param string $orderNumber
     *
     * @return OrderTransactionEntity
     */
    private function getOrderTransactionByNumber(Context $context, string $orderNumber): ?OrderTransactionEntity
    {
        $criteria = new Criteria();
        $criteria->addAssociation('transactions.paymentMethod.plugin');
        $filter = new EqualsFilter('order.orderNumber', $orderNumber);
        $criteria->addFilter($filter);

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        if ($order instanceof OrderEntity) {
            foreach ($order->getTransactions() as $transaction) {
                if ($transaction->getPaymentMethod()->getPlugin()->getBaseClass() === PevrPayeverIntegration::class) {
                    return $transaction;
                }
            }
        }

        return null;
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
            throw new \RuntimeException('Payment state is not found');
        }

        return $paymentState;
    }

    /**
     * Get Order.
     *
     * @param string $orderId
     *
     * @return OrderEntity
     * @throws \Exception
     */
    private function getOrder(string $orderId)
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('id', $orderId)
        );
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('deliveries');
        $criteria->addAssociation('deliveries.shippingMethod');

        /** @var OrderEntity[] $entities */
        $entities = $this->orderRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->getElements();

        foreach ($entities as $entity) {
            return $entity;
        }

        throw new \Exception('Order is not found: ' . $orderId);
    }

    /**
     * Mark order authorized.
     *
     * @since v6.4.15
     * @param string $transactionId
     * @param Context $context
     * @return void
     */
    private function authorize(string $transactionId, Context $context)
    {
        $this->logger->info('Order marked authorized', [$transactionId]);

        try {
            $this->orderTransactionStateHandler->authorize($transactionId, $context);
        } catch (IllegalTransitionException $exception) {
            $this->logger->critical($exception->getMessage() . ' ' . $exception->getTraceAsString());

            $orderTransaction = $this->getOrderTransactionById(
                $context,
                $transactionId
            );

            $data = [
                'id' => $orderTransaction->getId(),
                'stateId' => $this->getPaymentState(
                    StateMachineTransitionActions::ACTION_AUTHORIZE
                )->getId(),
            ];

            $this->orderTransactionRepository->update([$data], Context::createDefaultContext());
        }
    }

    /**
     * Mark order paid.
     *
     * @param string $transactionId
     * @param Context $context
     * @return void
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    private function paid(string $transactionId, Context $context)
    {
        $this->logger->info('Order marked paid', [$transactionId]);

        try {
            if (method_exists($this->orderTransactionStateHandler, 'paid')) {
                $this->orderTransactionStateHandler->paid($transactionId, $context);
            } else {
                $this->orderTransactionStateHandler->pay($transactionId, $context);
            }
        } catch (IllegalTransitionException $exception) {
            $this->logger->critical($exception->getMessage() . ' ' . $exception->getTraceAsString());

            $orderTransaction = $this->getOrderTransactionById(
                $context,
                $transactionId
            );

            $actionPaid = self::STATE_PAID;
            if (defined('Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions::ACTION_PAID')) { //phpcs:ignore
                $actionPaid = StateMachineTransitionActions::ACTION_PAID;
            }

            $data = [
                'id' => $orderTransaction->getId(),
                'stateId' => $this->getPaymentState(
                    $actionPaid
                )->getId(),
            ];

            $this->orderTransactionRepository->update([$data], Context::createDefaultContext());
        }
    }

    /**
     * Mark order refunded.
     *
     * @param string $transactionId
     * @param Context $context
     * @return void
     */
    private function refund(string $transactionId, Context $context)
    {
        $this->logger->info('Order marked refunded', [$transactionId]);

        try {
            $this->orderTransactionStateHandler->refund($transactionId, $context);
        } catch (IllegalTransitionException $exception) {
            $this->logger->critical($exception->getMessage() . ' ' . $exception->getTraceAsString());

            $orderTransaction = $this->getOrderTransactionById(
                $context,
                $transactionId
            );

            $data = [
                'id' => $orderTransaction->getId(),
                'stateId' => $this->getPaymentState(
                    StateMachineTransitionActions::ACTION_REFUND
                )->getId(),
            ];

            $this->orderTransactionRepository->update([$data], Context::createDefaultContext());
        }
    }

    /**
     * Mark order cancelled.
     *
     * @param string $transactionId
     * @param Context $context
     * @return void
     */
    private function cancel(string $transactionId, Context $context)
    {
        $this->logger->info('Order marked cancelled', [$transactionId]);

        try {
            $this->orderTransactionStateHandler->cancel($transactionId, $context);
        } catch (IllegalTransitionException $exception) {
            $this->logger->critical($exception->getMessage() . ' ' . $exception->getTraceAsString());

            $orderTransaction = $this->getOrderTransactionById(
                $context,
                $transactionId
            );

            $data = [
                'id' => $orderTransaction->getId(),
                'stateId' => $this->getPaymentState(
                    StateMachineTransitionActions::ACTION_CANCEL
                )->getId(),
            ];

            $this->orderTransactionRepository->update([$data], Context::createDefaultContext());
        }
    }
}
