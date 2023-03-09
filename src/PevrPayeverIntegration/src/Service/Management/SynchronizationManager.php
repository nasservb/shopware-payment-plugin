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

use Payever\ExternalIntegration\ThirdParty\Enum\ActionEnum;
use Payever\ExternalIntegration\ThirdParty\Enum\DirectionEnum;
use Payever\PayeverPayments\Service\DataAbstractionLayer\GenericTrait;
use Payever\PayeverPayments\Service\Helper\ConfigHelper;
use Payever\PayeverPayments\Service\PayeverApi\ProcessorFactory;
use Payever\PayeverPayments\Service\PayeverRegistry;
use Payever\PayeverPayments\Service\Transformer\InventoryTransformer;
use Payever\PayeverPayments\Service\Transformer\ProductTransformer;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;

class SynchronizationManager
{
    use GenericManagerTrait;
    use GenericTrait;

    /**
     * @var ProcessorFactory
     */
    private $processorFactory;

    /**
     * @var SynchronizationQueueManager
     */
    private $synchronizationQueueManager;

    /**
     * @var SubscriptionManager
     */
    private $subscriptionManager;

    /**
     * @var ProductTransformer
     */
    private $productTransformer;

    /**
     * @var InventoryTransformer
     */
    private $inventoryTransformer;

    /**
     * @param ProcessorFactory $processorFactory
     * @param SynchronizationQueueManager $synchronizationQueueManager
     * @param SubscriptionManager $subscriptionManager
     * @param ProductTransformer $productTransformer
     * @param InventoryTransformer $inventoryTransformer
     * @param ConfigHelper $configHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        ProcessorFactory $processorFactory,
        SynchronizationQueueManager $synchronizationQueueManager,
        SubscriptionManager $subscriptionManager,
        ProductTransformer $productTransformer,
        InventoryTransformer $inventoryTransformer,
        ConfigHelper $configHelper,
        LoggerInterface $logger
    ) {
        $this->processorFactory = $processorFactory;
        $this->synchronizationQueueManager = $synchronizationQueueManager;
        $this->subscriptionManager = $subscriptionManager;
        $this->productTransformer = $productTransformer;
        $this->inventoryTransformer = $inventoryTransformer;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
    }

    /**
     * @param ProductEntity $product
     * @param bool $isNew
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function handleProductSave(ProductEntity $product, bool $isNew = false): void
    {
        if ($this->isProductSupported($product)) {
            $this->handleAction(
                $isNew ? ActionEnum::ACTION_CREATE_PRODUCT : ActionEnum::ACTION_UPDATE_PRODUCT,
                DirectionEnum::OUTWARD,
                $this->productTransformer->transformFromShopwareIntoPayever($product)->toString()
            );
        }
    }

    /**
     * @param ProductEntity $product
     */
    public function handleProductDelete(ProductEntity $product): void
    {
        if ($this->isProductSupported($product)) {
            $this->handleAction(
                ActionEnum::ACTION_REMOVE_PRODUCT,
                DirectionEnum::OUTWARD,
                $this->productTransformer->transformRemovedShopwareIntoPayever($product)->toString()
            );
        }
    }

    /**
     * @param ProductEntity $product
     * @param int|null $delta
     */
    public function handleInventory(ProductEntity $product, int $delta = null): void
    {
        if ($this->isProductSupported($product)) {
            $action = ActionEnum::ACTION_SET_INVENTORY;
            if (null !== $delta) {
                $action = $delta < 0 ? ActionEnum::ACTION_SUBTRACT_INVENTORY : ActionEnum::ACTION_ADD_INVENTORY;
            }
            $this->handleAction(
                $action,
                DirectionEnum::OUTWARD,
                null !== $delta
                    ? $this->inventoryTransformer->transformFromShopwareToPayever($product, abs($delta))->toString()
                    : $this->inventoryTransformer->transformFromCreatedShopwareToPayever($product)->toString()
            );
        }
    }

    /**
     * @param string $action
     * @param string $direction
     * @param string $payload
     * @param bool $forceInstant
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function handleAction(
        string $action,
        string $direction,
        string $payload,
        bool $forceInstant = false
    ): void {
        $this->cleanMessages();
        if (
            !$this->isProductsSyncEnabled()
            || ($direction === DirectionEnum::OUTWARD && !$this->isProductsOutwardSyncEnabled())
        ) {
            $this->logMessages();
            return;
        }
        try {
            if (!$forceInstant && $this->configHelper->isCronMode()) {
                $this->synchronizationQueueManager->enqueueAction(
                    $action,
                    $direction,
                    $payload
                );
            } elseif ($direction === DirectionEnum::INWARD) {
                $this->processorFactory->getBidirectionalSyncActionProcessor()->processInwardAction($action, $payload);
            } else {
                try {
                    $this->processorFactory->getBidirectionalSyncActionProcessor()
                        ->processOutwardAction($action, $payload);
                } catch (\Exception $exception) {
                    $this->subscriptionManager->disable();
                    throw $exception;
                }
            }
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }
        $this->logMessages();
    }

    /**
     * @param ProductEntity $product
     * @return bool
     */
    private function isProductSupported(ProductEntity $product): bool
    {
        /** @var ProductEntity|null $lastProcessedProduct */
        $lastProcessedProduct = PayeverRegistry::get(PayeverRegistry::LAST_INWARD_PROCESSED_PRODUCT);

        return !$lastProcessedProduct || $product->getId() !== $lastProcessedProduct->getId();
    }
}
