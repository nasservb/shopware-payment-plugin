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
use Payever\PayeverPayments\OrderItems\OrderItemsEntity;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;

class OrderItemsManager
{
    use GenericTrait;

    const TYPE_PRODUCT = 'product';
    const TYPE_DISCOUNT = 'discount';
    const TYPE_SHIPPING = 'shipping';
    const TYPE_OTHER = 'other';

    /** @var EntityRepositoryInterface */
    private $orderRepository;

    /** @var EntityRepositoryInterface */
    private $orderItemsRepository;

    /** @var EntityRepositoryInterface */
    private $lineItemRepository;

    /** @var EntityRepositoryInterface */
    private $productRepository;

    /**
     * @param EntityRepositoryInterface $orderRepository
     * @param EntityRepositoryInterface $orderItemsRepository
     * @param EntityRepositoryInterface $lineItemRepository
     * @param EntityRepositoryInterface $productRepository
     */
    public function __construct(
        EntityRepositoryInterface $orderRepository,
        EntityRepositoryInterface $orderItemsRepository,
        EntityRepositoryInterface $lineItemRepository,
        EntityRepositoryInterface $productRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderItemsRepository = $orderItemsRepository;
        $this->lineItemRepository = $lineItemRepository;
        $this->productRepository = $productRepository;
    }

    /**
     * Allocate order items.
     *
     * @param string $orderId
     *
     * @return void
     * @throws \Exception
     */
    public function createItemsEntities(string $orderId): void
    {
        $order = $this->getOrder($orderId);
        $entities = $this->getItemsEntities($order);
        if (count($entities) === 0) {
            $this->fillOrderItems($order);
        }
    }

    /**
     * Get OrderItem.
     *
     * @param string $itemId
     *
     * @return OrderItemsEntity
     */
    public function getItem(string $itemId): OrderItemsEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter(OrderItemsEntity::FIELD_ID, $itemId)
        );

        return $this->orderItemsRepository
            ->search($criteria, $this->getContext())
            ->first();
    }

    /**
     * Add captured items.
     *
     * @param string $itemId
     * @param int $qty
     *
     * @return void
     */
    public function addCaptured(string $itemId, int $qty): void
    {
        $item = $this->getItem($itemId);

        if (($item->getQtyCaptured() + $qty) <= $item->getQuantity()) {
            $this->orderItemsRepository->update(
                [
                    [
                        OrderItemsEntity::FIELD_ID => $item->getId(),
                        OrderItemsEntity::FIELD_QTY_CAPTURED => $item->getQtyCaptured() + $qty
                    ]
                ],
                $this->getContext()
            );
        }
    }

    /**
     * Add cancelled items.
     *
     * @param string $itemId
     * @param int $qty
     *
     * @return void
     */
    public function addCancelled(string $itemId, int $qty): void
    {
        $item = $this->getItem($itemId);

        if (($item->getQtyCancelled() + $qty) <= $item->getQuantity()) {
            $this->orderItemsRepository->update(
                [
                    [
                        OrderItemsEntity::FIELD_ID => $item->getId(),
                        OrderItemsEntity::FIELD_QTY_CANCELLED => $item->getQtyCancelled() + $qty
                    ]
                ],
                $this->getContext()
            );
        }
    }

    /**
     * Add refunded items.
     *
     * @param string $itemId
     * @param int $qty
     *
     * @return void
     */
    public function addRefunded(string $itemId, int $qty): void
    {
        $item = $this->getItem($itemId);

        if (($item->getQtyRefunded() + $qty) <= $item->getQuantity()) {
            $this->orderItemsRepository->update(
                [
                    [
                        OrderItemsEntity::FIELD_ID => $item->getId(),
                        OrderItemsEntity::FIELD_QTY_REFUNDED => $item->getQtyRefunded() + $qty
                    ]
                ],
                $this->getContext()
            );
        }
    }

    /**
     * Get Order Items
     *
     * @param string $orderId
     *
     * @return array
     * @throws \Exception
     */
    public function getItems(string $orderId): array
    {
        $entities = $this->getItemsEntities($this->getOrder($orderId));

        $result = [];
        foreach ($entities as $entity) {
            /** @var OrderItemsEntity $entity */
            $result[] = [
                'id' => $entity->getId(),
                'item_type' => $entity->getItemType(),
                'item_id' => Uuid::fromBytesToHex($entity->getItemId()),
                'identifier' => $entity->getIdentifier(),
                'label' => $entity->getLabel(),
                'quantity' => $entity->getQuantity(),
                'unit_price' => $entity->getUnitPrice(),
                'total_price' => $entity->getTotalPrice(),
                'qty_captured' => $entity->getQtyCaptured(),
                'qty_cancelled' => $entity->getQtyCancelled(),
                'qty_refunded' => $entity->getQtyRefunded(),
                'can_be_captured' => $entity->getQuantity() - $entity->getQtyCaptured() - $entity->getQtyCancelled(),
                'can_be_cancelled' => $entity->getQuantity() - $entity->getQtyCaptured() - $entity->getQtyCancelled(),
                'can_be_refunded' => $entity->getQtyCaptured() - $entity->getQtyRefunded(),
            ];
        }

        return $result;
    }

    /**
     * Get Order items.
     *
     * @param string $orderId
     *
     * @return array
     * @throws \Exception
     */
    public function getOrderItems(string $orderId): array
    {
        $order = $this->getOrder($orderId);

        $lineItems = $order->getLineItems();

        $result = [];
        foreach ($lineItems as $lineItem) {
            /** @var OrderLineItemEntity $lineItem */
            if ($lineItem->getQuantity() === 0) {
                continue;
            }

            // Product type
            switch ($lineItem->getType()) {
                case LineItem::PRODUCT_LINE_ITEM_TYPE:
                    $type = self::TYPE_PRODUCT;
                    break;
                case LineItem::PROMOTION_LINE_ITEM_TYPE:
                case LineItem::DISCOUNT_LINE_ITEM:
                    $type = self::TYPE_DISCOUNT;
                    break;
                default:
                    $type = self::TYPE_OTHER;
                    break;
            }

            $result[] = [
                'item_id' => $lineItem->getId(),
                'identifier' => $lineItem->getIdentifier(),
                'type' => $type,
                'quantity' => $lineItem->getQuantity(),
                'label' => $lineItem->getLabel(),
                'unit_price' => $lineItem->getPrice()->getUnitPrice(),
                'total_price' => $lineItem->getPrice()->getTotalPrice(),
            ];
        }

        $deliveries = $order->getDeliveries();
        if ($deliveries) {
            foreach ($deliveries as $delivery) {
                /** @var OrderDeliveryEntity $delivery */
                $result[] = [
                    'item_id' => null,
                    'identifier' => $delivery->getShippingMethodId(),
                    'type' => self::TYPE_SHIPPING,
                    'quantity' => 1,
                    'label' => $delivery->getShippingMethod()->getName(),
                    'unit_price' => $delivery->getShippingCosts()->getTotalPrice(),
                    'total_price' => $delivery->getShippingCosts()->getTotalPrice(),
                ];
            }
        }

        // @todo Add discounts

        return $result;
    }

    /**
     * Restock Order item.
     *
     * @param string $itemId
     * @param int $quantity
     *
     * @return void
     * @throws \Exception
     */
    public function restockOrderItem(string $itemId, int $quantity): void
    {
        $item = $this->getItem($itemId);
        if ($item->getItemType() !== 'product') {
            return;
        }

        try {
            $orderItem = $this->getOrderLineItem(Uuid::fromBytesToHex($item->getItemId()));

            // Update product stock
            $productData = [
                'id' => $orderItem->getProduct()->getId(),
                'stock' => $orderItem->getProduct()->getStock() + $quantity
            ];
            $this->productRepository->update([$productData], Context::createDefaultContext());

            // Update line item stock
            $newQty = $orderItem->getQuantity() - $quantity;
            $data = [
                'id' => $orderItem->getId(),
                'quantity' => max($newQty, 0)
            ];

            $this->lineItemRepository->update([$data], Context::createDefaultContext());
        } catch (\Exception $exception) {
            // Silence is golden
        }
    }

    /**
     * Get Order Line Item.
     *
     * @param $itemId
     *
     * @return OrderLineItemEntity
     * @throws \Exception
     */
    private function getOrderLineItem($itemId): OrderLineItemEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('id', $itemId)
        );
        $criteria->addAssociation('product');

        $result = $this->lineItemRepository->search(
            $criteria,
            $this->getContext()
        );

        if ($result->count() > 0) {
            return $result->first();
        }

        throw new \Exception('Order line item is not found: ' . $itemId);
    }

    /**
     * @param OrderEntity $order
     *
     * @return OrderItemsEntity[]
     */
    private function getItemsEntities(OrderEntity $order): array
    {
        $orderId = $order->getId();

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter(OrderItemsEntity::FIELD_ORDER_ID, $orderId)
        );
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        /** @var OrderItemsEntity[] $entities */
        $entities = $this->orderItemsRepository
            ->search($criteria, $this->getContext())
            ->getEntities()
            ->getElements();

        return $entities;
    }

    private function fillOrderItems(OrderEntity $order): void
    {
        $orderId = $order->getId();

        $items = $this->getOrderItems($orderId);
        foreach ($items as $item) {
            $itemId = $item['item_id'] ? $item['item_id'] : $this->getRandomHex();

            $data = [
                OrderItemsEntity::FIELD_ID => $this->getRandomHex(),
                OrderItemsEntity::FIELD_ITEM_ID => Uuid::fromHexToBytes($itemId),
                OrderItemsEntity::FIELD_ORDER_ID => $orderId,
                OrderItemsEntity::FIELD_ITEM_TYPE => $item['type'],
                OrderItemsEntity::FIELD_IDENTIFIER => $item['identifier'],
                OrderItemsEntity::FIELD_LABEL => (string) $item['label'],
                OrderItemsEntity::FIELD_QUANTITY => $item['quantity'],
                OrderItemsEntity::FIELD_UNIT_PRICE => $item['unit_price'],
                OrderItemsEntity::FIELD_TOTAL_PRICE => $item['total_price'],
                OrderItemsEntity::FIELD_QTY_CAPTURED => 0,
                OrderItemsEntity::FIELD_QTY_CANCELLED => 0,
                OrderItemsEntity::FIELD_QTY_REFUNDED => 0,
            ];

            $this->orderItemsRepository->upsert(
                [
                    $data
                ],
                $this->getContext()
            );
        }
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
            ->search($criteria, $this->getContext())
            ->getEntities()
            ->getElements();

        foreach ($entities as $entity) {
            return $entity;
        }

        throw new \Exception('Order is not found: ' . $orderId);
    }
}
