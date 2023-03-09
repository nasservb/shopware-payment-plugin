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

namespace Payever\PayeverPayments\Service\Transformer;

use Payever\ExternalIntegration\Inventory\Http\RequestEntity\InventoryChangedRequestEntity;
use Payever\ExternalIntegration\Inventory\Http\RequestEntity\InventoryCreateRequestEntity;
use Payever\PayeverPayments\Service\Helper\ConfigHelper;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class InventoryTransformer
{
    use ProductAwareTrait;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @param EntityRepositoryInterface $productRepository
     * @param ConfigHelper $configHelper
     */
    public function __construct(
        EntityRepositoryInterface $productRepository,
        ConfigHelper $configHelper
    ) {
        $this->productRepository = $productRepository;
        $this->configHelper = $configHelper;
    }

    /**
     * @param string $productId
     * @param int $stock
     */
    public function updateStock(string $productId, int $stock)
    {
        $this->productRepository->update(
            [[
                'id' => $productId,
                'stock' => $stock,
            ]],
            $this->getContext()
        );
    }

    /**
     * @param ProductEntity $product
     * @param int $delta
     * @return InventoryChangedRequestEntity
     */
    public function transformFromShopwareToPayever(ProductEntity $product, int $delta): InventoryChangedRequestEntity
    {
        return (new InventoryChangedRequestEntity())
            ->setExternalId($this->configHelper->getProductsSyncExternalId())
            ->setSku($product->getProductNumber())
            ->setQuantity($delta);
    }

    /**
     * @param ProductEntity $product
     * @return InventoryCreateRequestEntity
     */
    public function transformFromCreatedShopwareToPayever(ProductEntity $product): InventoryCreateRequestEntity
    {
        return (new InventoryCreateRequestEntity())
            ->setExternalId($this->configHelper->getProductsSyncExternalId())
            ->setSku($product->getProductNumber())
            ->setStock($product->getStock());
    }
}
