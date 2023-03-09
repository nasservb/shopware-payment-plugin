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

namespace Payever\PayeverPayments\OrderTotals;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OrderTotalsEntity extends Entity
{
    use EntityIdTrait;

    public const FIELD_ID = 'id';
    public const FIELD_ORDER_ID = 'orderId';
    public const FIELD_CAPTURED_TOTAL = 'capturedTotal';
    public const FIELD_CANCELLED_TOTAL = 'cancelledTotal';
    public const FIELD_REFUNDED_TOTAL = 'refundedTotal';
    public const FIELD_MANUAL = 'manual';

    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var float
     */
    protected $capturedTotal;

    /**
     * @var float
     */
    protected $cancelledTotal;

    /**
     * @var float
     */
    protected $refundedTotal;

    /**
     * @var bool
     */
    protected $manual;

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
     * @return float
     */
    public function getCapturedTotal(): float
    {
        return $this->capturedTotal;
    }

    /**
     * @param float $capturedTotal
     * @return $this
     */
    public function setCapturedTotal(float $capturedTotal): self
    {
        $this->capturedTotal = $capturedTotal;

        return $this;
    }

    /**
     * @return float
     */
    public function getCancelledTotal(): float
    {
        return $this->cancelledTotal;
    }

    /**
     * @param float $cancelledTotal
     * @return $this
     */
    public function setCancelledTotal(float $cancelledTotal): self
    {
        $this->cancelledTotal = $cancelledTotal;

        return $this;
    }

    /**
     * @return float
     */
    public function getRefundedTotal(): float
    {
        return $this->refundedTotal;
    }

    /**
     * @param float $refundedTotal
     * @return $this
     */
    public function setRefundedTotal(float $refundedTotal): self
    {
        $this->refundedTotal = $refundedTotal;

        return $this;
    }

    /**
     * @return bool
     */
    public function isManual(): bool
    {
        return $this->manual;
    }

    /**
     * @param bool $flag
     * @return $this
     */
    public function setManual(bool $flag): self
    {
        $this->manual = $flag;

        return $this;
    }
}
