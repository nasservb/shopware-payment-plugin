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

namespace Payever\PayeverPayments\Service\Management;

use Payever\PayeverPayments\Service\DataAbstractionLayer\GenericTrait;
use Payever\PayeverPayments\OrderTotals\OrderTotalsEntity;
use Payever\PayeverPayments\Service\Helper\ConnectionHelper;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

class OrderTotalsManager
{
    use GenericTrait;

    /**
     * @var ConnectionHelper
     */
    private $connectionHelper;

    /** @var EntityRepositoryInterface */
    private $orderRepository;

    /** @var EntityRepositoryInterface */
    private $totalsRepository;

    /**
     * @param EntityRepositoryInterface $orderRepository
     * @param EntityRepositoryInterface $totalsRepository
     */
    public function __construct(
        ConnectionHelper $connectionHelper,
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $totalsRepository
    ) {
        $this->connectionHelper = $connectionHelper;
        $this->orderRepository = $orderRepository;
        $this->totalsRepository = $totalsRepository;
    }

    /**
     * Add Captured totals.
     *
     * @param OrderEntity $order
     * @param float $total
     * @param bool $isManual
     *
     * @return void
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function addCaptured(OrderEntity $order, float $total, bool $isManual = false): void
    {
        $this->addTotal($order, OrderTotalsEntity::FIELD_CAPTURED_TOTAL, $total, $isManual);
    }

    /**
     * Get Captured total.
     *
     * @param OrderEntity $order
     *
     * @return float
     */
    public function getCaptured(OrderEntity $order): float
    {
        $totals = $this->getTotals($order);

        if (!$totals) {
            return 0;
        }

        return $totals->getCapturedTotal();
    }

    /**
     * Add Cancelled totals.
     *
     * @param OrderEntity $order
     * @param float $total
     * @param bool $isManual
     *
     * @return void
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function addCancelled(OrderEntity $order, float $total, bool $isManual = false): void
    {
        $this->addTotal($order, OrderTotalsEntity::FIELD_CANCELLED_TOTAL, $total, $isManual);
    }

    /**
     * Get Cancelled total.
     *
     * @param OrderEntity $order
     *
     * @return float
     */
    public function getCancelled(OrderEntity $order): float
    {
        $totals = $this->getTotals($order);

        if (!$totals) {
            return 0;
        }

        return $totals->getCancelledTotal();
    }

    /**
     * Add Cancelled totals.
     *
     * @param OrderEntity $order
     * @param float $total
     * @param bool $isManual
     *
     * @return void
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function addRefunded(OrderEntity $order, float $total, bool $isManual = false): void
    {
        $this->addTotal($order, OrderTotalsEntity::FIELD_REFUNDED_TOTAL, $total, $isManual);
    }

    /**
     * Get Refunded total.
     *
     * @param OrderEntity $order
     *
     * @return float
     */
    public function getRefunded(OrderEntity $order): float
    {
        $totals = $this->getTotals($order);

        if (!$totals) {
            return 0;
        }

        return $totals->getRefundedTotal();
    }

    /**
     * Get available amount for capturing.
     *
     * @param OrderEntity $order
     *
     * @return float
     */
    public function getAvailableForCapturing(OrderEntity $order): float
    {
        $totals = $this->getTotals($order);
        if (!$totals) {
            return $order->getAmountTotal();
        }

        return $order->getAmountTotal() - ($totals->getCapturedTotal() + $totals->getCancelledTotal());
    }

    /**
     * Get available amount for cancelling.
     *
     * @param OrderEntity $order
     *
     * @return float
     */
    public function getAvailableForCancelling(OrderEntity $order): float
    {
        return $this->getAvailableForCapturing($order);
    }

    /**
     * Get available amount for refunding.
     *
     * @param OrderEntity $order
     *
     * @return float
     */
    public function getAvailableForRefunding(OrderEntity $order): float
    {
        $totals = $this->getTotals($order);
        if (!$totals) {
            return 0;
        }

        return $totals->getCapturedTotal() - $totals->getRefundedTotal();
    }

    /**
     * Check if order was applied manual action.
     *
     * @param OrderEntity $order
     * @return bool
     */
    public function isManual(OrderEntity $order): bool
    {
        $totals = $this->getTotals($order);
        if (!$totals) {
            return false;
        }

        return $totals->isManual();
    }

    /**
     * Add total.
     *
     * @param OrderEntity $order
     * @param string $field
     * @param float $total
     * @param bool $isManual
     *
     * @return void
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    private function addTotal(OrderEntity $order, string $field, float $total, bool $isManual = false): void
    {
        if (
            !in_array($field, [
            OrderTotalsEntity::FIELD_CAPTURED_TOTAL,
            OrderTotalsEntity::FIELD_CANCELLED_TOTAL,
            OrderTotalsEntity::FIELD_REFUNDED_TOTAL
            ])
        ) {
            throw new \Exception('Invalid field parameter.');
        }

        $totals = $this->getTotals($order);

        if (!$totals) {
            $data = [
                OrderTotalsEntity::FIELD_ID              => $this->getRandomHex(),
                OrderTotalsEntity::FIELD_ORDER_ID        => $order->getId(),
                OrderTotalsEntity::FIELD_CAPTURED_TOTAL  => 0,
                OrderTotalsEntity::FIELD_CANCELLED_TOTAL => 0,
                OrderTotalsEntity::FIELD_REFUNDED_TOTAL  => 0,
                OrderTotalsEntity::FIELD_MANUAL          => $isManual
            ];

            $this->totalsRepository->upsert(
                [
                    array_merge(
                        $data,
                        [$field => $total]
                    )
                ],
                $this->getContext()
            );

            return;
        }

        // Add total value
        switch ($field) {
            case OrderTotalsEntity::FIELD_CAPTURED_TOTAL:
                $amount = $totals->getCapturedTotal();
                break;
            case OrderTotalsEntity::FIELD_CANCELLED_TOTAL:
                $amount = $totals->getCancelledTotal();
                break;
            case OrderTotalsEntity::FIELD_REFUNDED_TOTAL:
                $amount = $totals->getRefundedTotal();
                break;
            default:
                $amount = 0;
                break;
        }

        $this->totalsRepository->upsert(
            [
                [
                    OrderTotalsEntity::FIELD_ID => $totals->getId(),
                    // Add manual flag if defined
                    OrderTotalsEntity::FIELD_MANUAL => $isManual || $totals->isManual(),
                    // Increase value
                    $field => $amount + $total
                ]
            ],
            $this->getContext()
        );
    }

    /**
     * @param OrderEntity $order
     *
     * @return OrderTotalsEntity|false
     */
    public function getTotals(OrderEntity $order)
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter(OrderTotalsEntity::FIELD_ORDER_ID, $order->getId())
        );

        /** @var OrderTotalsEntity[] $entities */
        $entities = $this->totalsRepository
            ->search($criteria, $this->getContext())
            ->getEntities()
            ->getElements();

        foreach ($entities as $totalEntity) {
            /** @var OrderTotalsEntity $totalEntity */
            return $totalEntity;
        }

        return false;
    }
}
