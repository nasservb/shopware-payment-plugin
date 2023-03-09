<?php

namespace Payever\PayeverPayments\Service\Item;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;

class Calculator
{
    private $discountMap = [];

    /**
     * @param OrderLineItemCollection $itemCollection
     * @param $item
     * @param int $quantity
     * @return float
     */
    public function calculateItemPrice(OrderLineItemCollection $itemCollection, $item, int $quantity = 1): float
    {
        $result = $item->getUnitPrice();
        if ($this->isPromotionItem($item) || !$this->getPromotionsCountAndFillDiscountMap($itemCollection)) {
            return $result;
        }

        if (isset($this->discountMap[$item->getIdentifier()])) {
            $result -= ($this->discountMap[$item->getIdentifier()]);
        }

        return round($result * $quantity, 2);
    }

    /**
     * @param $item
     * @return bool
     */
    private function isPromotionItem($item): bool
    {
        return $item->getType() == 'promotion';
    }

    /**
     * @param OrderLineItemCollection $itemCollection
     * @return int
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    private function getPromotionsCountAndFillDiscountMap(OrderLineItemCollection $itemCollection): int
    {
        $result = 0;
        foreach ($itemCollection as $item) {
            if ($this->isPromotionItem($item)) {
                foreach ($item->getPayload()['composition'] as $itemComposition) {
                    $discount = $itemComposition['discount'] / $itemComposition['quantity'];
                    if (!isset($this->discountMap[$itemComposition['id']])) {
                        $this->discountMap[$itemComposition['id']] = $discount;
                    } else {
                        $this->discountMap[$itemComposition['id']] += $discount;
                    }
                }
                $result++;
            }
        }

        return $result;
    }
}
