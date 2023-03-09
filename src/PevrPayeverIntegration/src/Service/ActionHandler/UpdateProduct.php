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

use Payever\ExternalIntegration\Products\Http\RequestEntity\ProductRequestEntity;
use Payever\PayeverPayments\Service\Transformer\ProductTransformer;
use Shopware\Core\Content\Product\ProductEntity;

class UpdateProduct extends AbstractActionHandler
{
    /**
     * @var ProductTransformer
     */
    protected $transformer;

    /**
     * @param ProductTransformer $transformer
     */
    public function __construct(ProductTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    /**
     * @return string
     */
    public function getSupportedAction(): string
    {
        return \Payever\ExternalIntegration\ThirdParty\Enum\ActionEnum::ACTION_UPDATE_PRODUCT;
    }

    /**
     * @param \Payever\ExternalIntegration\Core\Base\MessageEntity|ProductRequestEntity $entity
     * @throws \Exception
     */
    protected function process($entity): void
    {
        $type = $entity->getType();
        if ($type !== $this->transformer->getType()) {
            throw new \BadMethodCallException('Product type is not supported');
        }
        if ($entity->isVariant()) {
            throw new \BadMethodCallException(
                'Product is variant. This integration only supports full product payload'
            );
        }
        $product = $this->transformer->transformFromPayeverIntoShopwareProduct($entity);
        $this->assertActionIsNotStalled($product, $entity);
        $data = $this->transformer->getProductData($product);
        $this->pushToRegistry($product);
        $this->transformer->upsert([$data]);
    }

    /**
     * Increments updated count
     */
    protected function incrementActionResult(): void
    {
        $this->actionResult->incrementUpdated();
    }

    /**
     * @param ProductEntity $product
     * @param \Payever\ExternalIntegration\Core\Base\MessageEntity|ProductRequestEntity $requestEntity
     * @throws \BadMethodCallException
     */
    protected function assertActionIsNotStalled(ProductEntity $product, $requestEntity): void
    {
        $updatedAtFromRequest = $requestEntity->getUpdatedAt();
        $updatedAt = $product->getUpdatedAt();
        $isStalled = $updatedAtFromRequest instanceof \DateTime && $updatedAt instanceof \DateTime &&
            $updatedAtFromRequest->getTimestamp() <= $updatedAt->getTimestamp();
        if ($isStalled) {
            throw new \BadMethodCallException(sprintf(
                'Skip processing stalled action: %s <= %s',
                $updatedAtFromRequest->format(\DATE_RFC3339_EXTENDED),
                $updatedAt->format(\DATE_RFC3339_EXTENDED)
            ));
        }
    }
}
