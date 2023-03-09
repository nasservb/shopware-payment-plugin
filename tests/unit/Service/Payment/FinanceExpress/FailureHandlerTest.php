<?php

namespace Payever\PayeverPayments\tests\unit\Service\Payment\FinanceExpress;

use Payever\ExternalIntegration\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\PayeverPayments\Service\PayeverPayment;
use Payever\PayeverPayments\Service\Payment\FinanceExpress\FailureHandler;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class FailureHandlerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var MockObject|PayeverPayment
     */
    private $paymentHandler;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var FailureHandler
     */
    private $handler;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->productRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentHandler = $this->getMockBuilder(PayeverPayment::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->handler = new FailureHandler(
            $this->productRepository,
            $this->paymentHandler,
            $this->logger
        );
    }

    public function testGetSeoPath()
    {
        /** @var MockObject|SalesChannelContext $context */
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentHandler->expects($this->once())
            ->method('retrieveRequest')
            ->willReturn(
                $this->getMockBuilder(RetrievePaymentResultEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->productRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $context->expects($this->any())
            ->method('getContext')
            ->willReturn(
                $this->getMockBuilder(Context::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn(
                $entityCollection = $this->getMockBuilder(EntityCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entityCollection->expects($this->once())
            ->method('first')
            ->willReturn(
                $product = $this->getMockBuilder(ProductEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $product->expects($this->once())
            ->method('getSeoUrls')
            ->willReturn(
                $seoUrlCollection = $this->getMockBuilder(SeoUrlCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $seoUrlCollection->expects($this->once())
            ->method('first')
            ->willReturn(
                $seoUrlEntity = $this->getMockBuilder(SeoUrlEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $seoUrlEntity->expects($this->once())
            ->method('getSeoPathInfo')
            ->willReturn('/some/path/to/product/page');
        $this->assertNotEmpty($this->handler->getSeoPath($context, 'some-payment-uuid'));
    }

    public function testGetSeoPathCaseNoPaymentResult()
    {
        /** @var MockObject|SalesChannelContext $context */
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->assertEmpty($this->handler->getSeoPath($context, 'some-payment-uuid'));
    }

    public function testGetSeoPathCaseNoProduct()
    {
        /** @var MockObject|SalesChannelContext $context */
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentHandler->expects($this->once())
            ->method('retrieveRequest')
            ->willReturn(
                $this->getMockBuilder(RetrievePaymentResultEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->productRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $context->expects($this->any())
            ->method('getContext')
            ->willReturn(
                $this->getMockBuilder(Context::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn(
                $entityCollection = $this->getMockBuilder(EntityCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->assertEmpty($this->handler->getSeoPath($context, 'some-payment-uuid'));
    }

    public function testGetProductId()
    {
        /** @var MockObject|SalesChannelContext $context */
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentHandler->expects($this->once())
            ->method('retrieveRequest')
            ->willReturn(
                $this->getMockBuilder(RetrievePaymentResultEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->productRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $context->expects($this->any())
            ->method('getContext')
            ->willReturn(
                $this->getMockBuilder(Context::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn(
                $entityCollection = $this->getMockBuilder(EntityCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entityCollection->expects($this->once())
            ->method('first')
            ->willReturn(
                $product = $this->getMockBuilder(ProductEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $product->expects($this->once())
            ->method('getId')
            ->willReturn('some-product-uuid');
        $this->assertNotEmpty($this->handler->getProductId($context, 'some-payment-uuid'));
    }
}
