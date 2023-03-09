<?php

namespace Payever\PayeverPayments\tests\unit\Service\Management;

use Payever\ExternalIntegration\Core\Http\MessageEntity\GetCurrenciesResultEntity;
use Payever\ExternalIntegration\Core\Http\Response;
use Payever\ExternalIntegration\Core\Http\ResponseEntity\GetCurrenciesResponse;
use Payever\ExternalIntegration\Payments\PaymentsApiClient;
use Payever\ExternalIntegration\Products\Http\RequestEntity\ProductRequestEntity;
use Payever\PayeverPayments\Service\Management\PriceManager;
use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Tax\TaxEntity;

class PriceManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $currencyRepository;

    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $ruleRepository;

    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $taxRepository;

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var PriceManager
     */
    private $manager;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->currencyRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->ruleRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->taxRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->clientFactory = $this->getMockBuilder(ClientFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->manager = new PriceManager(
            $this->currencyRepository,
            $this->ruleRepository,
            $this->taxRepository,
            $this->clientFactory
        );
    }

    public function testGetCurrencyIsoCode()
    {
        $this->assertNotEmpty($this->manager->getCurrencyIsoCode());
    }

    public function testGetNetPrice()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getPrice')
            ->willReturn(
                $priceCollection = $this->getMockBuilder(PriceCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $priceCollection->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $price = $this->getMockBuilder(Price::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $price->expects($this->once())
            ->method('getCurrencyId')
            ->willReturn(Defaults::CURRENCY);
        $price->expects($this->once())
            ->method('getNet')
            ->willReturn($netPrice = 1.1);
        $this->assertEquals($netPrice, $this->manager->getNetPrice($product));
    }

    public function testGetGrossPrice()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getPrice')
            ->willReturn(
                $priceCollection = $this->getMockBuilder(PriceCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $priceCollection->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $price = $this->getMockBuilder(Price::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $price->expects($this->once())
            ->method('getCurrencyId')
            ->willReturn(Defaults::CURRENCY);
        $price->expects($this->once())
            ->method('getGross')
            ->willReturn($grossPrice = 2.2);
        $this->assertEquals($grossPrice, $this->manager->getGrossPrice($product));
    }

    public function testGetLinked()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getPrice')
            ->willReturn(
                $priceCollection = $this->getMockBuilder(PriceCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $priceCollection->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $price = $this->getMockBuilder(Price::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $price->expects($this->once())
            ->method('getCurrencyId')
            ->willReturn(Defaults::CURRENCY);
        $price->expects($this->once())
            ->method('getLinked')
            ->willReturn(true);
        $this->assertTrue($this->manager->getLinked($product));
    }

    public function testGetVatRate()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getTax')
            ->willReturn(
                $tax = $this->getMockBuilder(TaxEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $tax->expects($this->once())
            ->method('getTaxRate')
            ->willReturn($vatRate = 19.0);
        $this->assertEquals($vatRate, $this->manager->getVatRate($product));
    }

    public function testGetPreparedPriceCollection()
    {
        $this->currencyRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
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
                $currency = $this->getMockBuilder(CurrencyEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $currency->expects($this->any())
            ->method('getIsoCode')
            ->willReturn('GBP');
        $this->clientFactory->expects($this->once())
            ->method('getPaymentsApiClient')
            ->willReturn(
                $paymentsApiClient = $this->getMockBuilder(PaymentsApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentsApiClient->expects($this->once())
            ->method('getCurrenciesRequest')
            ->willReturn(
                $response = $this->getMockBuilder(Response::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $response->expects($this->once())
            ->method('getResponseEntity')
            ->willReturn(
                $responseEntity = $this->getMockBuilder(GetCurrenciesResponse::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $responseEntity->expects($this->once())
            ->method('__call')
            ->willReturn([
                'GBP' => $currencyResultEntity = $this->getMockBuilder(GetCurrenciesResultEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $currencyResultEntity->expects($this->once())
            ->method('__call')
            ->willReturn(0.9);
        $this->assertNotEmpty(
            $this->manager->getPreparedPriceCollection(
                $this->getMockBuilder(ProductEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock(),
                $this->getMockBuilder(ProductRequestEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            )
        );
    }

    public function testGetPreparedPriceCollectionCaseDefault()
    {
        $this->currencyRepository->expects($this->any())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->any())
            ->method('getEntities')
            ->willReturn(
                $entityCollection = $this->getMockBuilder(EntityCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entityCollection->expects($this->once())
            ->method('first')
            ->willReturn(
                $currency = $this->getMockBuilder(CurrencyEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $currency->expects($this->any())
            ->method('getIsoCode')
            ->willReturn(PriceManager::DEFAULT_CURRENCY_ISO_CODE);
        $this->assertNotEmpty(
            $this->manager->getPreparedPriceCollection(
                $this->getMockBuilder(ProductEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock(),
                $this->getMockBuilder(ProductRequestEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            )
        );
    }

    public function testGetPreparedProductPriceCollection()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|ProductRequestEntity $requestEntity */
        $requestEntity = $this->getMockBuilder(ProductRequestEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getPrice'])
            ->getMock();
        $requestEntity->expects($this->once())
            ->method('getPrice')
            ->willReturn(19.0);
        $this->currencyRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $currencyEntitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $currencyEntitySearchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn(
                $currencyEntityCollection = $this->getMockBuilder(EntityCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $currencyEntityCollection->expects($this->once())
            ->method('first')
            ->willReturn(
                $currency = $this->getMockBuilder(CurrencyEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $currency->expects($this->any())
            ->method('getIsoCode')
            ->willReturn(PriceManager::DEFAULT_CURRENCY_ISO_CODE);
        $this->ruleRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $ruleEntitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $ruleEntitySearchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn(
                $entityCollection = $this->getMockBuilder(EntityCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entityCollection->expects($this->once())
            ->method('first')
            ->willReturn(
                $defaultRule = $this->getMockBuilder(RuleEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $defaultRule->expects($this->any())
            ->method('getId')
            ->willReturn(($defaultRuleId = 'some-rule-id'));
        $product->expects($this->once())
            ->method('getPrices')
            ->willReturn($productPriceCollection = new ProductPriceCollection());
        /** @var MockObject|ProductPriceEntity $productPrice1 */
        $productPrice1 = $this->getMockBuilder(ProductPriceEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|ProductPriceEntity $productPrice2 */
        $productPrice2 = $this->getMockBuilder(ProductPriceEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $productPrice1->expects($this->once())
            ->method('getUniqueIdentifier')
            ->willReturn('product-price-id-1');
        $productPrice1->expects($this->once())
            ->method('getUniqueIdentifier')
            ->willReturn('product-price-id-2');
        $productPriceCollection->add($productPrice1);
        $productPriceCollection->add($productPrice2);
        $productPrice1->expects($this->once())
            ->method('getRuleId')
            ->willReturn($defaultRuleId);
        $priceCollection1 = new PriceCollection();
        $priceCollection1->add(
            $priceEntity1 = $this->getMockBuilder(Price::class)
                ->disableOriginalConstructor()
                ->getMock()
        );
        $productPrice1->expects($this->once())
            ->method('getPrice')
            ->willReturn($priceCollection1);
        $priceEntity1->expects($this->once())
            ->method('getCurrencyId')
            ->willReturn(Defaults::CURRENCY);
        $productPrice2->expects($this->once())
            ->method('getRuleId')
            ->willReturn('some-other-rule-id');
        $this->assertNotEmpty($this->manager->getPreparedProductPriceCollection($product, $requestEntity));
    }

    public function testGetCalculatedGrossPrice()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getPrice')
            ->willReturn(
                $priceCollection = $this->getMockBuilder(PriceCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $priceCollection->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $price = $this->getMockBuilder(Price::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $price->expects($this->once())
            ->method('getCurrencyId')
            ->willReturn(Defaults::CURRENCY);
        $price->expects($this->once())
            ->method('getNet')
            ->willReturn(1.1);
        $product->expects($this->once())
            ->method('getTax')
            ->willReturn(
                $tax = $this->getMockBuilder(TaxEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $tax->expects($this->once())
            ->method('getTaxRate')
            ->willReturn(19.0);

        $this->assertEquals(1.31, round($this->manager->getCalculatedGrossPrice($product), 2));
    }

    public function testGetPreparedTax()
    {
        /** @var MockObject|ProductRequestEntity $requestEntity */
        $requestEntity = $this->getMockBuilder(ProductRequestEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $requestEntity->expects($this->once())
            ->method('__call')
            ->willReturn(19.0);
        $this->taxRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn(
                $this->getMockBuilder(EntityCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->assertNotEmpty($this->manager->getPreparedTax($requestEntity));
    }

    public function testGetPriceData()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getPrice')
            ->willReturn($priceCollection = new PriceCollection());
        $price = $this->getMockBuilder(Price::class)
            ->disableOriginalConstructor()
            ->getMock();
        $priceCollection->add($price);
        $price->expects($this->once())
            ->method('getCurrencyId')
            ->willReturn(Defaults::CURRENCY);
        $price->expects($this->once())
            ->method('getNet')
            ->willReturn(1.00);
        $price->expects($this->once())
            ->method('getGross')
            ->willReturn(1.19);
        $price->expects($this->once())
            ->method('getLinked')
            ->willReturn(true);
        $this->assertNotEmpty($this->manager->getPriceData($product));
    }

    public function testGetPricesData()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $productPriceCollection = new ProductPriceCollection();
        /** @var MockObject|ProductPriceEntity $productPriceEntity */
        $productPriceEntity = $this->getMockBuilder(ProductPriceEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $productPriceEntity->expects($this->once())
            ->method('getUniqueIdentifier')
            ->willReturn('some-id');
        $productPriceCollection->add($productPriceEntity);
        $product->expects($this->once())
            ->method('getPrices')
            ->willReturn($productPriceCollection);
        $priceCollection = new PriceCollection();
        $priceCollection->add($priceEntity = $this->getMockBuilder(Price::class)
            ->disableOriginalConstructor()
            ->getMock());
        $productPriceEntity->expects($this->once())
            ->method('getPrice')
            ->willReturn($priceCollection);
        $priceEntity->expects($this->once())
            ->method('getCurrencyId')
            ->willReturn(Defaults::CURRENCY);
        $priceEntity->expects($this->once())
            ->method('getNet')
            ->willReturn(1.00);
        $priceEntity->expects($this->once())
            ->method('getGross')
            ->willReturn(1.19);
        $priceEntity->expects($this->once())
            ->method('getLinked')
            ->willReturn(true);
        $productPriceEntity->expects($this->once())
            ->method('getId')
            ->willReturn('some-id');
        $productPriceEntity->expects($this->once())
            ->method('getRuleId')
            ->willReturn('some-rule-id');
        $productPriceEntity->expects($this->once())
            ->method('getQuantityStart')
            ->willReturn(1);
        $this->assertNotEmpty($this->manager->getPricesData($product));
    }

    public function testGetTaxData()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getTax')
            ->willReturn(
                $tax = $this->getMockBuilder(TaxEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $tax->expects($this->once())
            ->method('getId')
            ->willReturn('some-id');
        $this->assertNotEmpty($this->manager->getTaxData($product));
    }
}
