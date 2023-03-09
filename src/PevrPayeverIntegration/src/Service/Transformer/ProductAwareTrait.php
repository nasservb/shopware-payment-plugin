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

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

trait ProductAwareTrait
{
    use \Payever\PayeverPayments\Service\DataAbstractionLayer\GenericTrait;

    /**
     * @var EntityRepositoryInterface
     */
    protected $productRepository;

    /**
     * @param string $sku
     * @param bool $createNew
     * @return ProductEntity|null
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public function getProduct(string $sku, bool $createNew = false): ?ProductEntity
    {
        /** @var ProductEntity|null $product */
        $product = $this->productRepository->search(
            $this->getBaseProductCriteria()
                ->setLimit(1)
                ->addFilter(new EqualsFilter('productNumber', $sku)),
            $this->getContext()
        )
            ->getEntities()
            ->first();
        if (!$product && $createNew) {
            $product = new ProductEntity();
            $product->setId($this->getRandomHex());
            $product->setStock(0);
        }

        return $product;
    }

    /**
     * @param array $ids
     * @return Criteria
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    public function getBaseProductCriteria(array $ids = [])
    {
        return (new Criteria($ids))
            ->addAssociation('visibilities')
            ->addAssociation('categories')
            ->addAssociation('mainCategories.category')
            ->addAssociation('configuratorSettings')
            ->addAssociation('options')
            ->addAssociation('options.group')
            ->addAssociation('options.productConfiguratorSettings')
            ->addAssociation('options.productOptions')
            ->addAssociation('options.configuratorSetting')
            ->addAssociation('price')
            ->addAssociation('prices')
            ->addAssociation('children')
            ->addAssociation('children.options')
            ->addAssociation('children.options.group')
            ->addAssociation('children.prices')
            ->addAssociation('children.media')
            ->addAssociation('children.cover')
            ->addAssociation('media')
            ->addAssociation('cover');
    }
}
