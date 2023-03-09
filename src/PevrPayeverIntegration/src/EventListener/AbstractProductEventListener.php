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

use Payever\PayeverPayments\Service\Management\SynchronizationManager;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

abstract class AbstractProductEventListener
{
    use \Payever\PayeverPayments\Service\Transformer\ProductAwareTrait;

    /**
     * @var SynchronizationManager
     */
    protected $synchronizationManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param EntityRepositoryInterface $productRepository
     * @param SynchronizationManager $synchronizationManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        EntityRepositoryInterface $productRepository,
        SynchronizationManager $synchronizationManager,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->synchronizationManager = $synchronizationManager;
        $this->logger = $logger;
    }

    /**
     * @param string|null $productId
     * @return ProductEntity|null
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    protected function getUncachedProduct(string $productId = null): ?ProductEntity
    {
        $product = null;
        if ($productId) {
            $repository = $this->productRepository;
            $baseCriteria = $this->getBaseProductCriteria([$productId]);
            // @codeCoverageIgnoreStart
            $callable = static function (Context $context) use ($repository, $baseCriteria) {
                return $repository->search($baseCriteria, $context)
                    ->getEntities()
                    ->first();
            };
            $context = $this->getContext();
            if (method_exists($context, 'disableCache')) {
                /** @var ProductEntity|null $product */
                $product = $context->disableCache($callable);
            } else {
                $product = $callable($context);
            }
            // @codeCoverageIgnoreEnd
        }

        return $product;
    }
}
