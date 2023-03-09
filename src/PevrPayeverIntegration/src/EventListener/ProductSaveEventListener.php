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

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductSaveEventListener extends AbstractProductEventListener implements EventSubscriberInterface
{
    /**
     * @var int[]
     */
    private $affectedProductStocks = [];

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductSave',
        ];
    }

    /**
     * @param PreWriteValidationEvent $event
     */
    public function preValidate(PreWriteValidationEvent $event): void
    {
        if (!$this->synchronizationManager->isProductsSyncEnabled()) {
            return;
        }
        foreach ($event->getCommands() as $writeCommand) {
            if ($writeCommand->getDefinition()->getClass() === ProductDefinition::class) {
                $primaryKey = $writeCommand->getEntityExistence()->getPrimaryKey();
                $productId = $primaryKey['id'] ?? null;
                $payload = $writeCommand->getPayload();
                if ($writeCommand instanceof UpdateCommand) {
                    if (array_key_exists('stock', $payload)) {
                        $product = $this->getUncachedProduct($productId);
                        if ($product) {
                            $this->affectedProductStocks[$productId] = $product->getStock();
                        }
                    }
                }
            }
        }
    }

    /**
     * @param EntityWrittenEvent $event
     */
    public function onProductSave(EntityWrittenEvent $event): void
    {
        if (!$this->shouldProcessEvent($event)) {
            $this->affectedProductStocks = [];

            return;
        }
        $writeResults = $event->getWriteResults();
        $writeResult = $writeResults[0];
        $isNew = $writeResult->getOperation() === EntityWriteResult::OPERATION_INSERT;
        $existence = $writeResult->getExistence();
        $primaryKey = $existence->getPrimaryKey();
        $productId = $primaryKey['id'] ?? null;
        $product = $this->getUncachedProduct($productId);
        if ($product) {
            $parentId = $product->getParentId();
            $parentProduct = $parentId ? $this->getUncachedProduct($parentId) : null;
            $parentProduct
                ? $this->synchronizationManager->handleProductSave($parentProduct)
                : $this->synchronizationManager->handleProductSave($product, $isNew);
            $delta = null;
            if (array_key_exists($productId, $this->affectedProductStocks)) {
                $stockOrigin = $this->affectedProductStocks[$productId];
                $delta = $product->getStock() - $stockOrigin;
            }
            if ($isNew || $delta) {
                $this->synchronizationManager->handleInventory($product, $delta);
            }
        }
        $this->affectedProductStocks = [];
    }

    /**
     * @param EntityWrittenEvent $event
     * @return bool
     */
    private function shouldProcessEvent(EntityWrittenEvent $event): bool
    {
        $result = false;
        if ($this->synchronizationManager->isProductsSyncEnabled() && !$event->getErrors()) {
            $writeResults = $event->getWriteResults();
            if (!empty($writeResults[0])) {
                $writeResult = $writeResults[0];
                $operations = [EntityWriteResult::OPERATION_INSERT, EntityWriteResult::OPERATION_UPDATE];
                $result = in_array($writeResult->getOperation(), $operations) && (bool) $writeResult->getExistence();
            }
        }

        return $result;
    }

    /**
     * @param array $affectedProductStocks
     * @internal
     */
    public function setAffectedProductStocks(array $affectedProductStocks): void
    {
        $this->affectedProductStocks = $affectedProductStocks;
    }
}
