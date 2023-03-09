<?php

namespace Payever\PayeverPayments\tests\unit\Service\Generator;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\ResultStatement;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\PayeverPayments\Service\Generator\OrderGenerator;
use Payever\PayeverPayments\Service\Helper\ConnectionHelper;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class OrderGeneratorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var MockObject|SalesChannelContextFactory|AbstractSalesChannelContextFactory
     */
    private $contextFactory;

    /**
     * @var MockObject|CartService
     */
    private $cartService;

    /**
     * @var MockObject|OrderConverter
     */
    private $orderConverter;

    /**
     * @var MockObject|EntityWriterInterface
     */
    private $writer;

    /**
     * @var MockObject|OrderDefinition
     */
    private $orderDefinition;

    /**
     * @var MockObject|ConnectionHelper
     */
    private $connectionHelper;

    /**
     * @var OrderGenerator
     */
    private $generator;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->orderRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $contextFactoryClassName = class_exists(
            'Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory'
        )
            ? SalesChannelContextFactory::class
            : 'Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory';
        $this->contextFactory = $this->getMockBuilder($contextFactoryClassName)
            ->disableOriginalConstructor()
            ->getMock();
        $this->cartService = $this->getMockBuilder(CartService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderConverter = $this->getMockBuilder(OrderConverter::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->writer = $this->getMockBuilder(EntityWriterInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderDefinition = $this->getMockBuilder(OrderDefinition::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->connectionHelper = $this->getMockBuilder(ConnectionHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->generator = new OrderGenerator(
            $this->orderRepository,
            $this->contextFactory,
            $this->cartService,
            $this->orderConverter,
            $this->writer,
            $this->orderDefinition,
            $this->connectionHelper
        );
    }

    public function testGenerate()
    {
        /** @var MockObject|SalesChannelContext $context */
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|CustomerEntity $customer */
        $customer = $this->getMockBuilder(CustomerEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|RetrievePaymentResultEntity $paymentResult */
        $paymentResult = $this->getMockBuilder(RetrievePaymentResultEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getTotal'])
            ->getMock();
        $context->expects($this->any())
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());
        $this->connectionHelper->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['id' => 'some-product-uuid']);
        $context->expects($this->once())
            ->method('getToken')
            ->willReturn('some-token');
        $this->connectionHelper->expects($this->once())
            ->method('executeQuery')
            ->willReturn(
                $this->getMockBuilder(
                    class_exists('Doctrine\DBAL\Driver\Result')
                        ? Result::class
                        : ResultStatement::class
                )
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->connectionHelper->expects($this->once())
            ->method('fetchOne')
            ->willReturn('some-payment-method-uuid');
        $context->expects($this->once())
            ->method('getSalesChannel')
            ->willReturn(
                $salesChannel = $this->getMockBuilder(SalesChannelEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $salesChannel->expects($this->once())
            ->method('getId')
            ->willReturn('some-sales-channel-id');
        $this->contextFactory->expects($this->once())
            ->method('create')
            ->willReturn(
                $this->getMockBuilder(SalesChannelContext::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->cartService->expects($this->once())
            ->method('createNew')
            ->willReturn(
                $cart = $this->getMockBuilder(Cart::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->cartService->expects($this->once())
            ->method('recalculate')
            ->willReturn($cart);
        $paymentResult->expects($this->once())
            ->method('getTotal')
            ->willReturn($totalPrice = 11.11);
        $cart->expects($this->once())
            ->method('getPrice')
            ->willReturn(
                $cartPrice = $this->getMockBuilder(CartPrice::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $cartPrice->expects($this->once())
            ->method('getTotalPrice')
            ->willReturn($totalPrice);
        $this->orderConverter->expects($this->once())
            ->method('convertToOrder')
            ->willReturn(['id' => 'some-order-uuid']);
        $this->writer->expects($this->once())
            ->method('upsert');
        $this->orderRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('first')
            ->willReturn(
                $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->assertNotEmpty($this->generator->generate($context, $customer, $paymentResult));
    }

    public function testGenerateCaseException()
    {
        /** @var MockObject|SalesChannelContext $context */
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|CustomerEntity $customer */
        $customer = $this->getMockBuilder(CustomerEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|RetrievePaymentResultEntity $paymentResult */
        $paymentResult = $this->getMockBuilder(RetrievePaymentResultEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getTotal'])
            ->getMock();
        $context->expects($this->any())
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());
        $this->connectionHelper->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['id' => 'some-product-uuid']);
        $context->expects($this->once())
            ->method('getToken')
            ->willReturn('some-token');
        $this->connectionHelper->expects($this->once())
            ->method('executeQuery')
            ->willReturn(
                $this->getMockBuilder(
                    class_exists('Doctrine\DBAL\Driver\Result')
                        ? Result::class
                        : ResultStatement::class
                )
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->connectionHelper->expects($this->once())
            ->method('fetchOne')
            ->willReturn('some-payment-method-uuid');
        $this->contextFactory->expects($this->once())
            ->method('create')
            ->willReturn(
                $this->getMockBuilder(SalesChannelContext::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->cartService->expects($this->once())
            ->method('createNew')
            ->willReturn(
                $cart = $this->getMockBuilder(Cart::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->cartService->expects($this->once())
            ->method('recalculate')
            ->willReturn($cart);
        $paymentResult->expects($this->once())
            ->method('getTotal')
            ->willReturn(11.11);
        $cart->expects($this->once())
            ->method('getPrice')
            ->willReturn(
                $cartPrice = $this->getMockBuilder(CartPrice::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $cartPrice->expects($this->once())
            ->method('getTotalPrice')
            ->willReturn(11.0);
        $this->expectException(\BadMethodCallException::class);
        $this->generator->generate($context, $customer, $paymentResult);
    }
}
