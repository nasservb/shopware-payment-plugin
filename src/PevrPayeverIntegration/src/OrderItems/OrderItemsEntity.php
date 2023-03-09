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

namespace Payever\PayeverPayments\OrderItems;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OrderItemsEntity extends Entity
{
    use EntityIdTrait;

    public const FIELD_ID = 'id';
    public const FIELD_ORDER_ID = 'orderId';
    public const FIELD_ITEM_TYPE = 'itemType';
    public const FIELD_ITEM_ID = 'itemId';
    public const FIELD_IDENTIFIER = 'identifier';
    public const FIELD_LABEL = 'label';
    public const FIELD_QUANTITY = 'quantity';
    public const FIELD_UNIT_PRICE = 'unitPrice';
    public const FIELD_TOTAL_PRICE = 'totalPrice';
    public const FIELD_QTY_CAPTURED = 'qtyCaptured';
    public const FIELD_QTY_CANCELLED = 'qtyCancelled';
    public const FIELD_QTY_REFUNDED = 'qtyRefunded';

    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var string
     */
    protected $itemType;

    /**
     * @var string
     */
    protected $itemId;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var float
     */
    protected $unitPrice;

    /**
     * @var float
     */
    protected $totalPrice;

    /**
     * @var int
     */
    protected $qtyCaptured;

    /**
     * @var int
     */
    protected $qtyCancelled;

    /**
     * @var int
     */
    protected $qtyRefunded;

    /**
     * @return string
     */
    public function getOrderId(): string
    {
        return $this->orderId;
    }

    /**
     * @param string $orderId
     * @return $this
     */
    public function setOrderId(string $orderId): self
    {
        $this->orderId = $orderId;

        return $this;
    }

    /**
     * @return string
     */
    public function getItemType(): string
    {
        return $this->itemType;
    }

    /**
     * @param string $itemType
     * @return $this
     */
    public function setItemType(string $itemType): self
    {
        $this->itemType = $itemType;

        return $this;
    }

    /**
     * @return string
     */
    public function getItemId(): string
    {
        return $this->itemId;
    }

    /**
     * @param string $itemId
     * @return $this
     */
    public function setItemId(string $itemId): self
    {
        $this->itemId = $itemId;

        return $this;
    }

    /**
     * @return string
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @param string $identifier
     * @return $this
     */
    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @param string $label
     * @return $this
     */
    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return int
     */
    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @param int $quantity
     * @return $this
     */
    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;

        return $this;
    }

    /**
     * @return float
     */
    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    /**
     * @param float $unitPrice
     * @return $this
     */
    public function setUnitPrice(float $unitPrice): self
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    /**
     * @return float
     */
    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    /**
     * @param float $totalPrice
     * @return $this
     */
    public function setTotalPrice(float $totalPrice): self
    {
        $this->totalPrice = $totalPrice;

        return $this;
    }

    /**
     * @return int
     */
    public function getQtyCaptured(): int
    {
        return $this->qtyCaptured;
    }

    /**
     * @param int $qtyCaptured
     * @return $this
     */
    public function setQtyCaptured(int $qtyCaptured): self
    {
        $this->qtyCaptured = $qtyCaptured;

        return $this;
    }

    /**
     * @return int
     */
    public function getQtyCancelled(): int
    {
        return $this->qtyCancelled;
    }

    /**
     * @param int $qtyCancelled
     * @return $this
     */
    public function setQtyCancelled(int $qtyCancelled): self
    {
        $this->qtyCancelled = $qtyCancelled;

        return $this;
    }

    /**
     * @return int
     */
    public function getQtyRefunded(): int
    {
        return $this->qtyRefunded;
    }

    /**
     * @param int $qtyRefunded
     * @return $this
     */
    public function setQtyRefunded(int $qtyRefunded): self
    {
        $this->qtyRefunded = $qtyRefunded;

        return $this;
    }
}
