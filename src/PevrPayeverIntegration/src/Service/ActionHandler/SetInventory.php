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

namespace Payever\PayeverPayments\Service\ActionHandler;

use Payever\ExternalIntegration\Core\Base\MessageEntity;
use Payever\ExternalIntegration\Inventory\Http\MessageEntity\InventoryChangedEntity;
use Payever\PayeverPayments\Service\Transformer\InventoryTransformer;
use Shopware\Core\Content\Product\ProductEntity;

class SetInventory extends AbstractActionHandler
{
    /** @var ProductEntity|null */
    protected $product;

    /**
     * @var InventoryTransformer
     */
    protected $transformer;

    /**
     * @var bool
     */
    protected $considerDiff = false;

    /**
     * @var int
     */
    protected $sign = 1;

    /**
     * @param InventoryTransformer $transformer
     */
    public function __construct(InventoryTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    /**
     * @return string
     */
    public function getSupportedAction(): string
    {
        return \Payever\ExternalIntegration\ThirdParty\Enum\ActionEnum::ACTION_SET_INVENTORY;
    }

    /**
     * @param MessageEntity|InventoryChangedEntity $entity
     */
    protected function process($entity): void
    {
        $this->changeStock($entity);
    }

    /**
     * @param InventoryChangedEntity $entity
     */
    protected function changeStock(InventoryChangedEntity $entity): void
    {
        $this->logger->info('Inventory to be changed', ['data' => $entity->toString()]);
        $sku = $entity->getSku();
        $this->product = $this->transformer->getProduct($sku);
        if (!$this->product) {
            $this->logger->warning('Product is not found', ['sku' => $sku]);

            return;
        }
        $this->pushToRegistry($this->product);
        if (!$this->considerDiff || !$this->product->getAvailable()) {
            $this->transformer->updateStock($this->product->getId(), (int) $entity->getStock());

            return;
        }
        $diff = (int) abs($entity->getQuantity()) * $this->sign;
        $this->transformer->updateStock($this->product->getId(), $this->product->getStock() + $diff);
    }

    /**
     * Increments created count
     */
    protected function incrementActionResult(): void
    {
        $this->product !== null ? $this->actionResult->incrementCreated() : $this->actionResult->incrementSkipped();
    }
}
