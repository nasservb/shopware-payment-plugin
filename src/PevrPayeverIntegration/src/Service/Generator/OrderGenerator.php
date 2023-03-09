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

namespace Payever\PayeverPayments\Service\Generator;

use Payever\ExternalIntegration\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\PayeverPayments\PevrPayeverIntegration;
use Payever\PayeverPayments\Service\Helper\ConnectionHelper;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @see \Shopware\Core\Framework\Demodata\Generator\OrderGenerator
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class OrderGenerator
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var SalesChannelContextFactory|AbstractSalesChannelContextFactory
     */
    private $contextFactory;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var OrderConverter
     */
    private $orderConverter;

    /**
     * @var EntityWriterInterface
     */
    private $writer;

    /**
     * @var OrderDefinition
     */
    private $orderDefinition;

    /**
     * @var ConnectionHelper
     */
    private $connectionHelper;

    /**
     * @var string|null
     */
    private $orderId;

    /**
     * AbstractSalesChannelContextFactory instead of SalesChannelContextFactory is involved in shopware 6.4,
     * so do not cast $contextFactory argument by type
     *
     * @param EntityRepositoryInterface $orderRepository
     * @param SalesChannelContextFactory|AbstractSalesChannelContextFactory $contextFactory
     * @param CartService $cartService
     * @param OrderConverter $orderConverter
     * @param EntityWriterInterface $writer
     * @param OrderDefinition $orderDefinition
     * @param ConnectionHelper $connectionHelper
     */
    public function __construct(
        EntityRepositoryInterface $orderRepository,
        $contextFactory,
        CartService $cartService,
        OrderConverter $orderConverter,
        EntityWriterInterface $writer,
        OrderDefinition $orderDefinition,
        ConnectionHelper $connectionHelper
    ) {
        $this->orderRepository = $orderRepository;
        $this->contextFactory = $contextFactory;
        $this->cartService = $cartService;
        $this->orderConverter = $orderConverter;
        $this->writer = $writer;
        $this->orderDefinition = $orderDefinition;
        $this->connectionHelper = $connectionHelper;
    }

    /**
     * @param SalesChannelContext $context
     * @param CustomerEntity $customer
     * @param RetrievePaymentResultEntity $paymentResult
     * @return OrderEntity
     * @throws \Doctrine\DBAL\DBALException
     */
    public function generate(
        SalesChannelContext $context,
        CustomerEntity $customer,
        RetrievePaymentResultEntity $paymentResult
    ): OrderEntity {
        $this->orderId = null;
        $context->getContext()->scope(
            Context::SYSTEM_SCOPE,
            function () use ($context, $paymentResult, $customer) {
                $this->write($context, $paymentResult, $customer);
            }
        );
        /** @var OrderEntity $order */
        $order = $this->orderRepository->search(new Criteria([$this->orderId]), $context->getContext())->first();

        return $order;
    }

    /**
     * @param SalesChannelContext $context
     * @param RetrievePaymentResultEntity $paymentResult
     * @param CustomerEntity $customer
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Cart\Exception\LineItemNotStackableException
     * @throws \Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException
     * @throws \Shopware\Core\Checkout\Order\Exception\DeliveryWithoutAddressException
     */
    private function write(
        SalesChannelContext $context,
        RetrievePaymentResultEntity $paymentResult,
        CustomerEntity $customer
    ): void {
        $writeContext = WriteContext::createFromContext($context->getContext());
        $sql = <<<SQL
SELECT LOWER(HEX(product.id)) AS id, product.price, trans.name
FROM product
LEFT JOIN product_translation trans ON product.id = trans.product_id
WHERE product.product_number = ?
SQL;
        $product = $this->connectionHelper->fetchAssociative($sql, [$paymentResult->getReference()]);
        $productId = $product['id'];
        $payload = [];
        $lineItems = new LineItemCollection([
            (new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, 1))
                ->setStackable(true)
                ->setRemovable(true)
        ]);
        $token = $context->getToken();
        $options = [
            SalesChannelContextService::CUSTOMER_ID => $customer->getId(),
        ];
        $sql = <<<SQL
SELECT LOWER(HEX(payment_method_id)) FROM payment_method_translation
WHERE JSON_CONTAINS(custom_fields, JSON_QUOTE(:payever_method_code), :custom_field_name)
SQL;
        $statement = $this->connectionHelper->executeQuery(
            $sql,
            [
                'payever_method_code' => $paymentResult->getPaymentType(),
                'custom_field_name' => sprintf('$.%s', PevrPayeverIntegration::CUSTOM_FIELD_METHOD_CODE),
            ]
        );
        $paymentMethodId = $this->connectionHelper->fetchOne($statement);
        if ($paymentMethodId) {
            $options[SalesChannelContextService::PAYMENT_METHOD_ID] = $paymentMethodId;
        }
        $salesChannelContext = $this->contextFactory->create($token, $context->getSalesChannel()->getId(), $options);
        $salesChannelContext->setTaxState(CartPrice::TAX_STATE_GROSS);
        $cart = $this->cartService->createNew($token, 'payever-finance-express');
        $cart->addLineItems($lineItems);
        $cart = $this->cartService->recalculate($cart, $salesChannelContext);
        $paymentResultTotalPrice = (float)$paymentResult->getTotal();
        $totalPrice = $cart->getPrice()->getTotalPrice();
        if ($paymentResultTotalPrice !== $totalPrice) {
            throw new \BadMethodCallException(sprintf(
                'The amount really paid (%s) is not equal to the cart amount (%s).',
                $paymentResultTotalPrice,
                $totalPrice
            ));
        }
        $tempOrder = $this->orderConverter->convertToOrder($cart, $salesChannelContext, new OrderConversionContext());
        $tempOrder['orderDateTime'] = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $payload[] = $tempOrder;
        $this->writer->upsert($this->orderDefinition, $payload, $writeContext);
        $this->orderId = $tempOrder['id'];
        $this->cartService->remove($cart, $productId, $context);
    }
}
