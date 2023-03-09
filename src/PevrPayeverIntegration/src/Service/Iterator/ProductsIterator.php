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

namespace Payever\PayeverPayments\Service\Iterator;

use Payever\ExternalIntegration\Products\Base\ProductsIteratorInterface;
use Payever\PayeverPayments\Service\Transformer\ProductTransformer;
use Shopware\Core\Content\Product\ProductEntity;

class ProductsIterator extends \ArrayIterator implements ProductsIteratorInterface
{
    /**
     * @var ProductTransformer
     */
    private $productTransformer;

    /**
     * @param ProductTransformer $productTransformer
     * @param array $productExportCollection
     */
    public function __construct(ProductTransformer $productTransformer, array $productExportCollection)
    {
        parent::__construct($productExportCollection);
        $this->productTransformer = $productTransformer;
    }

    /**
     * @return \Payever\ExternalIntegration\Products\Http\RequestEntity\ProductRequestEntity|mixed
     */
    public function current()
    {
        $item = parent::current();
        if ($item instanceof ProductEntity) {
            $item = $this->productTransformer->transformFromShopwareIntoPayever($item);
        }

        return $item;
    }
}
