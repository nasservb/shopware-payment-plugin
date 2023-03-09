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

namespace Payever\PayeverPayments\Service\Payment\FinanceExpress;

use Payever\ExternalIntegration\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\PayeverPayments\Service\PayeverPayment;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class FailureHandler
{
    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var PayeverPayment
     */
    private $paymentHandler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var array
     */
    private $paymentResultList = [];

    /**
     * @param EntityRepositoryInterface $productRepository
     * @param PayeverPayment $paymentHandler
     * @param LoggerInterface $logger
     */
    public function __construct(
        EntityRepositoryInterface $productRepository,
        PayeverPayment $paymentHandler,
        LoggerInterface $logger
    ) {
        $this->productRepository = $productRepository;
        $this->paymentHandler = $paymentHandler;
        $this->logger = $logger;
    }

    /**
     * @param SalesChannelContext $context
     * @param string $paymentId
     * @return string|null
     * @throws \Exception
     */
    public function getSeoPath(SalesChannelContext $context, string $paymentId): ?string
    {
        $path = null;
        $product = $this->getProduct($context, $paymentId);
        if ($product) {
            $seoUrls = $product->getSeoUrls();
            if ($seoUrls) {
                $seoUrlEntity = $seoUrls->first();
                if ($seoUrlEntity instanceof SeoUrlEntity) {
                    $path = '/' . $seoUrlEntity->getSeoPathInfo();
                }
            }
        }

        return $path;
    }

    /**
     * @param SalesChannelContext $context
     * @param string $paymentId
     * @return string|null
     * @throws \Exception
     */
    public function getProductId(SalesChannelContext $context, string $paymentId): ?string
    {
        $productId = null;
        $product = $this->getProduct($context, $paymentId);
        if ($product) {
            $productId = $product->getId();
        }

        return $productId;
    }

    /**
     * @param SalesChannelContext $context
     * @param string $paymentId
     * @return ProductEntity|null
     * @throws \Exception
     */
    private function getProduct(SalesChannelContext $context, string $paymentId): ?ProductEntity
    {
        $paymentResult = $this->getPaymentResult($context, $paymentId);
        if (!$paymentResult) {
            $this->logger->warning('Unable to retrieve payment result entity');
            return null;
        }
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('productNumber', $paymentResult->getReference()))
            ->addAssociation('seoUrls');
        /** @var ProductEntity|null $product */
        $product = $this->productRepository->search($criteria, $context->getContext())->getEntities()->first();

        return $product;
    }

    /**
     * @param SalesChannelContext $context
     * @param string $paymentId
     * @return RetrievePaymentResultEntity|null
     * @throws \Exception
     */
    private function getPaymentResult(SalesChannelContext $context, string $paymentId): ?RetrievePaymentResultEntity
    {
        if (empty($this->paymentResultList[$paymentId])) {
            $this->paymentResultList[$paymentId] = $this->paymentHandler->retrieveRequest($paymentId, $context);
        }

        return $this->paymentResultList[$paymentId];
    }
}
