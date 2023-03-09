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

namespace Payever\PayeverPayments\Service\Management;

use Payever\ExternalIntegration\Core\Http\MessageEntity\GetCurrenciesResultEntity;
use Payever\ExternalIntegration\Core\Http\Response;
use Payever\ExternalIntegration\Core\Http\ResponseEntity\GetCurrenciesResponse;
use Payever\ExternalIntegration\Products\Http\RequestEntity\ProductRequestEntity;
use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Tax\TaxEntity;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class PriceManager
{
    use \Payever\PayeverPayments\Service\DataAbstractionLayer\GenericTrait;

    public const DEFAULT_CURRENCY_ISO_CODE = 'EUR';
    private const DEFAULT_CURRENCY_DECIMAL_PRECISION = 2;
    private const DEFAULT_VAT_RATE = 7.00;

    /**
     * @var EntityRepositoryInterface
     */
    private $currencyRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $ruleRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $taxRepository;

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var CurrencyEntity|null
     */
    private $defaultCurrency;

    /**
     * @var RuleEntity|null
     */
    private $defaultRule;

    /**
     * @var CurrencyEntity[]
     */
    private $currencyCache = [];

    /**
     * @var GetCurrenciesResultEntity[]
     */
    private $currencyList;

    /**
     * @param EntityRepositoryInterface $currencyRepository
     * @param EntityRepositoryInterface $ruleRepository
     * @param EntityRepositoryInterface $taxRepository
     * @param ClientFactory $clientFactory
     */
    public function __construct(
        EntityRepositoryInterface $currencyRepository,
        EntityRepositoryInterface $ruleRepository,
        EntityRepositoryInterface $taxRepository,
        ClientFactory $clientFactory
    ) {
        $this->currencyRepository = $currencyRepository;
        $this->ruleRepository = $ruleRepository;
        $this->taxRepository = $taxRepository;
        $this->clientFactory = $clientFactory;
    }

    /**
     * @return string
     */
    public function getCurrencyIsoCode(): string
    {
        return self::DEFAULT_CURRENCY_ISO_CODE;
    }

    /**
     * @param ProductEntity $product
     * @return bool
     */
    public function hasPrice(ProductEntity $product): bool
    {
        return null !== $this->getPrice($product);
    }

    /**
     * @param ProductEntity $product
     * @return float
     */
    public function getNetPrice(ProductEntity $product): float
    {
        $netPrice = 0.0;
        $price = $this->getPrice($product);
        if ($price) {
            $netPrice = $price->getNet();
        }

        return $netPrice;
    }

    /**
     * @param ProductEntity $product
     * @return float
     */
    public function getGrossPrice(ProductEntity $product): float
    {
        $grossPrice = 0.0;
        $price = $this->getPrice($product);
        if ($price) {
            $grossPrice = $price->getGross();
        }

        return $grossPrice;
    }

    /**
     * @param ProductEntity $product
     * @return bool
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getLinked(ProductEntity $product): bool
    {
        $linked = false;
        $price = $this->getPrice($product);
        if ($price) {
            $linked = $price->getLinked();
        }

        return $linked;
    }

    /**
     * @param ProductEntity $product
     * @return Price|null
     */
    private function getPrice(ProductEntity $product): ?Price
    {
        $result = null;
        $priceCollection = $product->getPrice();
        if ($priceCollection) {
            foreach ($priceCollection->getElements() as $price) {
                if ($price->getCurrencyId() === Defaults::CURRENCY) {
                    $result = $price;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * @param ProductEntity $product
     * @return float
     */
    public function getVatRate(ProductEntity $product): float
    {
        $vatRate = 0.0;
        $tax = $product->getTax();
        if ($tax) {
            $vatRate = $tax->getTaxRate();
        }

        return $vatRate;
    }

    /**
     * @param ProductEntity $product
     * @param ProductRequestEntity $requestEntity
     * @return PriceCollection
     * @throws \Exception
     */
    public function getPreparedPriceCollection(
        ProductEntity $product,
        ProductRequestEntity $requestEntity
    ): PriceCollection {
        $priceCollection = $product->getPrice() ?? new PriceCollection();
        $sourceIsoCode = $requestEntity->getCurrency() ?? self::DEFAULT_CURRENCY_ISO_CODE;
        $price = (float) $requestEntity->getPrice();
        $salesPrice = $requestEntity->getOnSales() ? (float) $requestEntity->getSalePrice() : null;
        $vatRate = (float) $requestEntity->getVatRate();
        $currency = $this->getCurrencyByIsoCode($sourceIsoCode);
        if (!$currency) {
            $currency = $this->getDefaultCurrency();
        }
        if ($currency) {
            $priceCollection->clear();
            $this->addPriceToCollection($priceCollection, $currency, $vatRate, $price, $salesPrice);
        }

        return $priceCollection;
    }

    /**
     * @param ProductEntity $product
     * @param ProductRequestEntity $requestEntity
     * @return ProductPriceCollection
     * @throws \Exception
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function getPreparedProductPriceCollection(
        ProductEntity $product,
        ProductRequestEntity $requestEntity
    ): ProductPriceCollection {
        $collection = new ProductPriceCollection();
        $sourceIsoCode = $requestEntity->getCurrency() ?? self::DEFAULT_CURRENCY_ISO_CODE;
        $price = (float) $requestEntity->getPrice();
        $salesPrice = $requestEntity->getOnSales() ? $requestEntity->getSalePrice() : null;
        $vatRate = (float) $requestEntity->getVatRate();
        $defaultCurrency = $this->getDefaultCurrency();
        $defaultRuleId = $this->getDefaultRuleId();
        $isProcessable = $defaultCurrency && $defaultRuleId;
        $assignedProductPrices = $product->getPrices();
        if ($assignedProductPrices) {
            $collection = $assignedProductPrices;
        }
        if ($isProcessable) {
            $priceRuleFound = false;
            foreach ($collection as $productPrice) {
                if ($defaultRuleId === $productPrice->getRuleId()) {
                    $priceRuleFound = true;
                    $priceCollection = $productPrice->getPrice();
                    if ($priceCollection) {
                        foreach ($priceCollection as $priceEntity) {
                            $currency = $this->getCurrencyById($priceEntity->getCurrencyId());
                            if ($currency) {
                                $currencyRate = $this->getConversationRate($sourceIsoCode, $currency->getIsoCode());
                                $decimalPrecision = method_exists($currency, 'getItemRounding')
                                    ? $currency->getItemRounding()->getDecimals()
                                    : $currency->getDecimalPrecision();
                                $this->populatePriceEntity(
                                    $priceEntity,
                                    $decimalPrecision,
                                    $currencyRate,
                                    $vatRate,
                                    $price,
                                    $salesPrice
                                );
                            }
                        }
                    }
                }
            }
            if (!$priceRuleFound) {
                $productPrice = new ProductPriceEntity();
                $productPrice->setId($this->getRandomHex());
                $productPrice->setProduct($product);
                $productPrice->setQuantityStart(1);
                $productPrice->setRuleId($defaultRuleId);
                $productPrice->setPrice($priceCollection = new PriceCollection());
                $collection->add($productPrice);
                $currency = $this->getCurrencyByIsoCode($sourceIsoCode);
                if ($currency) {
                    $this->addPriceToCollection($priceCollection, $currency, $vatRate, $price, $salesPrice);
                }
            }
        }

        return $collection;
    }

    /**
     * @return CurrencyEntity|null
     */
    private function getDefaultCurrency(): ?CurrencyEntity
    {
        if (null === $this->defaultCurrency) {
            $this->defaultCurrency = $this->getCurrencyById(Defaults::CURRENCY);
        }

        return $this->defaultCurrency;
    }

    /**
     * @param string $currencyId
     * @return CurrencyEntity|null
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function getCurrencyById(string $currencyId): ?CurrencyEntity
    {
        if (empty($this->currencyCache[$currencyId])) {
            /** @var CurrencyEntity|null $currency */
            $currency = $this->currencyRepository->search(
                (new Criteria([$currencyId])),
                $this->getContext()
            )
                ->getEntities()
                ->first();
            if ($currency) {
                $this->currencyCache[$currencyId] = $currency;
                $this->currencyCache[$currency->getIsoCode()] = $currency;
            }
        }

        return $this->currencyCache[$currencyId] ?? null;
    }

    /**
     * @param string $isoCode
     * @return CurrencyEntity|null
     */
    private function getCurrencyByIsoCode(string $isoCode): ?CurrencyEntity
    {
        if (empty($this->currencyCache[$isoCode])) {
            /** @var CurrencyEntity|null $currency */
            $currency = $this->currencyRepository->search(
                (new Criteria())
                    ->addFilter(new EqualsFilter('isoCode', $isoCode)),
                $this->getContext()
            )
                ->getEntities()
                ->first();
            if ($currency) {
                $this->currencyCache[$currency->getId()] = $currency;
                $this->currencyCache[$isoCode] = $currency;
            }
        }

        return $this->currencyCache[$isoCode] ?? null;
    }

    /**
     * @return string|null
     */
    private function getDefaultRuleId(): ?string
    {
        if (null === $this->defaultRule) {
            $this->defaultRule = $this->ruleRepository->search(
                (new Criteria())
                    ->setLimit(1)
                ->addFilter(new EqualsFilter('name', 'All customers')),
                $this->getContext()
            )
                ->getEntities()
                ->first();
            if (!$this->defaultRule) {
                $this->defaultRule = $this->ruleRepository->search(
                    (new Criteria())
                        ->setLimit(1),
                    $this->getContext()
                )
                    ->getEntities()
                    ->first();
            }
        }

        return $this->defaultRule ? $this->defaultRule->getId() : null;
    }

    /**
     * @param string $sourceIsoCode
     * @param string $targetIsoCode
     * @return float
     * @throws \Exception
     */
    private function getConversationRate(string $sourceIsoCode, string $targetIsoCode): float
    {
        return $this->getCurrencyRate($sourceIsoCode) * $this->getCurrencyRate($targetIsoCode);
    }

    /**
     * @param PriceCollection $priceCollection
     * @param CurrencyEntity $currency
     * @param float $vatRate
     * @param float $price
     * @param float|null $salesPrice
     * @throws \Exception
     */
    private function addPriceToCollection(
        PriceCollection $priceCollection,
        CurrencyEntity $currency,
        float $vatRate,
        float $price,
        float $salesPrice = null
    ): void {
        $priceEntity = new Price($currency->getId(), 0.0, 0.0, false);
        $decimalPrecision = method_exists($currency, 'getItemRounding')
            ? $currency->getItemRounding()->getDecimals()
            : $currency->getDecimalPrecision();
        $this->populatePriceEntity(
            $priceEntity,
            $decimalPrecision,
            1,
            $vatRate,
            $price,
            $salesPrice
        );
        $priceCollection->add($priceEntity);
        if ($currency->getId() !== Defaults::CURRENCY) {
            $priceEntity = new Price(Defaults::CURRENCY, 0.0, 0.0, false);
            $currencyRate = $this->getCurrencyRate($currency->getIsoCode());
            $this->populatePriceEntity(
                $priceEntity,
                self::DEFAULT_CURRENCY_DECIMAL_PRECISION,
                $currencyRate,
                $vatRate,
                $price,
                $salesPrice
            );
            $priceCollection->add($priceEntity);
        }
    }

    /**
     * @param Price $priceEntity
     * @param int $decimalPrecision
     * @param float $currencyRate
     * @param float $vatRate
     * @param float $price
     * @param float|null $salesPrice
     */
    private function populatePriceEntity(
        Price $priceEntity,
        int $decimalPrecision,
        float $currencyRate,
        float $vatRate,
        float $price,
        float $salesPrice = null
    ): void {
        $net = $this->getCalculatedNetPrice($currencyRate, $price, $vatRate);
        $priceEntity->setNet(round($net, $decimalPrecision));
        $gross = $salesPrice
            ? $this->getCalculatedPrice($currencyRate, $salesPrice)
            : $this->getCalculatedPrice($currencyRate, $price);
        $priceEntity->setGross(round($gross, $decimalPrecision));
        $priceEntity->setLinked(!$salesPrice);
    }

    /**
     * @param string $currencyIsoCode
     * @return float
     * @throws \Exception
     */
    private function getCurrencyRate(string $currencyIsoCode): float
    {
        $rate = 1.0;
        if ($currencyIsoCode !== self::DEFAULT_CURRENCY_ISO_CODE) {
            $result = $this->getCurrenciesResultEntityList();
            $currencyResultEntity = $result[$currencyIsoCode] ?? null;
            if ($currencyResultEntity instanceof GetCurrenciesResultEntity) {
                $currencyRate = $currencyResultEntity->getRate();
                if (null !== $currencyRate) {
                    $rate *= $currencyRate;
                }
            }
        }

        return $rate;
    }

    /**
     * @return GetCurrenciesResultEntity[]
     * @throws \Exception
     */
    private function getCurrenciesResultEntityList(): array
    {
        if (null === $this->currencyList) {
            /** @var Response $response */
            $response = $this->clientFactory->getPaymentsApiClient()->getCurrenciesRequest();
            /** @var GetCurrenciesResponse $responseEntity */
            $responseEntity = $response->getResponseEntity();
            $this->currencyList = $responseEntity->getResult();
        }

        return $this->currencyList ?? [];
    }

    /**
     * @param float $currencyRate
     * @param float $price
     * @return float
     */
    protected function getCalculatedPrice(float $currencyRate, float $price): float
    {
        return $currencyRate ? $price / $currencyRate : $price;
    }

    /**
     * @param float $currencyRate
     * @param float $price
     * @param float $vatRate
     * @return float
     */
    protected function getCalculatedNetPrice(float $currencyRate, float $price, float $vatRate): float
    {
        $currencyRate = $currencyRate ?: 1.0;

        return $price / $currencyRate - ($price / $currencyRate) / ((100 + $vatRate) / 100) * ($vatRate / 100);
    }

    /**
     * @param ProductEntity $product
     * @return float
     */
    public function getCalculatedGrossPrice(ProductEntity $product): float
    {
        $net = $this->getNetPrice($product);
        $vatRate = $this->getVatRate($product);

        return $net + ($net * $vatRate / 100);
    }

    /**
     * @param ProductRequestEntity $requestEntity
     * @return TaxEntity
     */
    public function getPreparedTax(ProductRequestEntity $requestEntity): TaxEntity
    {
        $vatRate = (float) $requestEntity->getVatRate();
        $vatRate = $vatRate ?: self::DEFAULT_VAT_RATE;
        $tax = $this->taxRepository->search(
            (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('taxRate', $vatRate)),
            $this->getContext()
        )
            ->getEntities()
            ->first();
        if (!$tax) {
            $tax = new TaxEntity();
            $data = [
                'id' => $this->getRandomHex(),
                'name' => $vatRate . '%',
                'taxRate' => $vatRate,
            ];
            $tax->assign($data);
            $this->taxRepository->upsert([$data], $this->getContext());
        }

        return $tax;
    }

    /**
     * @param ProductEntity $product
     * @return array|null
     */
    public function getPriceData(ProductEntity $product): ?array
    {
        $data = [];
        $priceCollection = $product->getPrice();
        if ($priceCollection) {
            foreach ($priceCollection as $price) {
                $data[] = [
                    'currencyId' => $price->getCurrencyId(),
                    'net' => $price->getNet(),
                    'gross' => $price->getGross(),
                    'linked' => $price->getLinked(),
                ];
            }
        }

        return $data;
    }

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getPricesData(ProductEntity $product): array
    {
        $data = [];
        $productPriceCollection = $product->getPrices();
        if ($productPriceCollection) {
            foreach ($productPriceCollection as $productPriceEntity) {
                $prices = [];
                $priceCollection = $productPriceEntity->getPrice();
                if ($priceCollection) {
                    foreach ($priceCollection as $priceEntity) {
                        $prices[] = [
                            'currencyId' => $priceEntity->getCurrencyId(),
                            'net' => $priceEntity->getNet(),
                            'gross' => $priceEntity->getGross(),
                            'linked' => $priceEntity->getLinked(),
                        ];
                    }
                }
                $data[] = [
                    'id' => $productPriceEntity->getId(),
                    'ruleId' => $productPriceEntity->getRuleId(),
                    'quantityStart' => $productPriceEntity->getQuantityStart(),
                    'quantityEnd' => $productPriceEntity->getQuantityEnd(),
                    'price' => $prices,
                ];
            }
        }

        return $data;
    }

    /**
     * @param ProductEntity $product
     * @return array|null
     */
    public function getTaxData(ProductEntity $product): ?array
    {
        $data = null;
        $tax = $product->getTax();
        if ($tax) {
            $data = ['id' => $tax->getId()];
        }

        return $data;
    }
}
