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
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductDeleteEventListener extends AbstractProductEventListener implements EventSubscriberInterface
{
    /**
     * @var ProductEntity[]
     */
    private $productsToRemove = [];

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'preValidate',
            ProductEvents::PRODUCT_DELETED_EVENT => 'onProductDelete',
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
                if ($writeCommand instanceof DeleteCommand) {
                    $product = $this->getUncachedProduct($productId);
                    if ($product) {
                        $this->productsToRemove[$productId] = $product;
                    }
                }
            }
        }
    }

    /**
     * @param EntityDeletedEvent $event
     */
    public function onProductDelete(EntityDeletedEvent $event): void
    {
        if (!$this->synchronizationManager->isProductsSyncEnabled()) {
            $this->productsToRemove = [];

            return;
        }
        if (!$event->getErrors()) {
            $writeResults = $event->getWriteResults();
            if (!empty($writeResults[0])) {
                $writeResult = $writeResults[0];
                $existence = $writeResult->getExistence();
                if ($existence) {
                    $primaryKey = $existence->getPrimaryKey();
                    $productId = $primaryKey['id'] ?? null;
                    if (!empty($this->productsToRemove[$productId])) {
                        $productToRemove = $this->productsToRemove[$productId];
                        $parentId = $productToRemove->getParentId();
                        $parentProduct = $parentId ? $this->getUncachedProduct($parentId) : null;
                        $parentProduct
                            ? $this->synchronizationManager->handleProductSave($parentProduct)
                            : $this->synchronizationManager->handleProductDelete($productToRemove);
                    }
                }
            }
        }
        $this->productsToRemove = [];
    }

    /**
     * @internal
     * @param array $productsToRemove
     */
    public function setProductsToRemove(array $productsToRemove): void
    {
        $this->productsToRemove = $productsToRemove;
    }
}
