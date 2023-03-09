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

namespace Payever\PayeverPayments\Service\Payment;

use Payever\ExternalIntegration\Payments\Converter\PaymentOptionConverter;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\ConvertedPaymentOptionEntity;
use Payever\ExternalIntegration\Payments\Http\ResponseEntity\ListPaymentOptionsWithVariantsResponse;
use Payever\ExternalIntegration\Payments\PaymentsApiClient;
use Payever\PayeverPayments\PevrPayeverIntegration;
use Payever\PayeverPayments\Service\PayeverPayment;
use Payever\PayeverPayments\Service\Setting\SettingsServiceInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class PaymentOptionsService
{
    private const PAYEVER_PREFIX = 'payever_';

    /** @var PluginIdProvider */
    private $pluginIdProvider;

    /** @var EntityRepositoryInterface */
    private $paymentMethodRepository;

    /** @var PaymentsApiClient */
    private $paymentsApiClient;

    /** @var EntityRepositoryInterface */
    private $countryRepo;

    /** @var EntityRepositoryInterface */
    private $currencyRepo;

    /** @var EntityRepositoryInterface */
    private $salesChannelRepo;

    /** @var SettingsServiceInterface */
    private $settingsService;

    /**
     * @param PluginIdProvider $pluginIdProvider
     * @param EntityRepositoryInterface $paymentMethodRepository
     * @param PaymentsApiClient $paymentsApiClient
     * @param EntityRepositoryInterface $countryRepo
     * @param EntityRepositoryInterface $currencyRepo
     * @param EntityRepositoryInterface $salesChannelRepo
     * @param SettingsServiceInterface $settingsService
     */
    public function __construct(
        PluginIdProvider $pluginIdProvider,
        EntityRepositoryInterface $paymentMethodRepository,
        PaymentsApiClient $paymentsApiClient,
        EntityRepositoryInterface $countryRepo,
        EntityRepositoryInterface $currencyRepo,
        EntityRepositoryInterface $salesChannelRepo,
        SettingsServiceInterface $settingsService
    ) {
        $this->pluginIdProvider = $pluginIdProvider;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->paymentsApiClient = $paymentsApiClient;
        $this->countryRepo = $countryRepo;
        $this->currencyRepo = $currencyRepo;
        $this->salesChannelRepo = $salesChannelRepo;
        $this->settingsService = $settingsService;
    }

    /**
     * @param Context $context
     *
     * @throws \Payever\PayeverPayments\Service\Setting\Exception\PayeverSettingsInvalidException
     */
    public function deactivateActivePaymentOptions(Context $context): void
    {
        $activeMethods = $this->settingsService->getSettings()->getActivePayeverMethods();
        $data = [];
        foreach (array_keys($activeMethods) as $uuid) {
            $data[] = [
                'id' => $uuid,
                'active' => false,
            ];
        }

        if ($data) {
            $this->paymentMethodRepository->update($data, $context);
        }
    }

    /**
     * @param Context $context
     *
     * @return array
     *
     * @throws \Exception
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function synchronizePaymentOptions(Context $context): array
    {
        $businessUuids = $this->collectBusinessUuids($context);

        if (empty($businessUuids)) {
            throw new \UnexpectedValueException(
                'Please enter Business UUID for at least one Sales Channel configuration'
            );
        }

        $convertedOptions = [];
        foreach ($businessUuids as $businessUuid) {
            /** @var ListPaymentOptionsWithVariantsResponse $response */
            $response = $this->paymentsApiClient->listPaymentOptionsWithVariantsRequest([], $businessUuid)
                ->getResponseEntity();
            $convertedOptions = array_merge(
                $convertedOptions,
                PaymentOptionConverter::convertPaymentOptionVariants($response->getResult())
            );
        }

        $activeMethods = [];
        $noticeMessages = [];
        $checkAddressEqualityMethods = [];
        $shippingNotAllowedMethods = [];

        foreach ($convertedOptions as $paymentMethod) {
            $methodCode = $paymentMethod->getPaymentMethod();
            $needToAddVariant = isset($activeMethods[md5($this->addPayeverPrefix($methodCode))]);

            /** @var ConvertedPaymentOptionEntity $paymentMethod */
            $isActiveMethod = $this->upsertPaymentMethod($paymentMethod, $context, $needToAddVariant);

            if ($isActiveMethod) {
                $activeMethods[md5($this->addPayeverPrefix($methodCode))] = $methodCode;
            } else {
                $noticeMessages[] = sprintf(
                    'In order to display %s method in the checkout please switch on one '
                        . 'of the following currencies in your store: %s',
                    $paymentMethod->getName(),
                    implode(', ', $paymentMethod->getOptions()->getCurrencies())
                );
            }

            if ($paymentMethod->getShippingAddressEquality()) {
                $checkAddressEqualityMethods[md5($this->addPayeverPrefix($methodCode))] = $methodCode;
            }

            if (!$paymentMethod->getShippingAddressAllowed()) {
                $shippingNotAllowedMethods[md5($this->addPayeverPrefix($methodCode))] = $methodCode;
            }
        }

        $this->settingsService->updateSettings([
            'activePayeverMethods' => $activeMethods,
            'checkAddressEqualityMethods' => $checkAddressEqualityMethods,
            'shippingNotAllowedMethods' => $shippingNotAllowedMethods
        ]);

        return $noticeMessages;
    }

    /**
     * @param Context $context
     *
     * @return array|string[]
     *
     * @throws \Payever\PayeverPayments\Service\Setting\Exception\PayeverSettingsInvalidException
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function collectBusinessUuids(Context $context): array
    {
        $result = [];
        $searchResult = $this->salesChannelRepo->search(new Criteria(), $context);
        /** @var SalesChannelEntity[] $salesChannels */
        $salesChannels = $searchResult->getElements();
        foreach ($salesChannels as $salesChannel) {
            $config = $this->settingsService->getSettings($salesChannel->getId());

            if ($config->getBusinessUuid()) {
                $result[] = $config->getBusinessUuid();
            }
        }

        return array_unique($result);
    }

    /**
     * @param ConvertedPaymentOptionEntity $paymentMethod
     * @param Context $context
     * @param bool $addVariant
     * @return bool
     * @SuppressWarnings(PHPMD.ElseExpression)
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    private function upsertPaymentMethod(
        ConvertedPaymentOptionEntity $paymentMethod,
        Context $context,
        $addVariant = false
    ): bool {
        $methodCode = $paymentMethod->getPaymentMethod();
        $methodName = $paymentMethod->getName();
        $paymentMethodOptions = $paymentMethod->getOptions();
        if ($addVariant) {
            $methodCode .= sprintf('-%s', $paymentMethod->getVariantId());
            $methodName .= sprintf(' - %s', $paymentMethod->getVariantName());
        }
        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass(
            PevrPayeverIntegration::class,
            $context
        );
        $data = [
            'id' => md5($this->addPayeverPrefix($methodCode)),
            'name' => $methodName,
            'description' => $paymentMethod->getDescriptionOffer(),
            'active' => $paymentMethod->getStatus(),
            'afterOrderEnabled' => true,
            'handlerIdentifier' => PayeverPayment::class,
            'pluginId' => $pluginId,
            'availabilityRule' => [
                'id' => md5($methodCode),
                'name' => $methodName . ' rules',
                'priority' => 0,
                'conditions' => [
                    [
                        'id' => md5('cartCartAmount>=' . $methodCode),
                        'type' => 'cartCartAmount',
                        'value' => [
                            'operator' => '>=',
                            'amount' => $paymentMethod->getMin(),
                        ],
                    ],
                    [
                        'id' => md5('cartCartAmount<=' . $methodCode),
                        'type' => 'cartCartAmount',
                        'value' => [
                            'operator' => '<=',
                            'amount' => $paymentMethod->getMax(),
                        ],
                    ],
                    [
                        'id' => md5('customerBillingCountry=' . $methodCode),
                        'type' => 'customerBillingCountry',
                        'value' => [
                            'operator' => '=',
                            'countryIds' => $this->getCountryIdsByCodes(
                                $paymentMethodOptions->getCountries(),
                                $context
                            ),
                        ],
                    ],
                ],
            ],
        ];

        if ($paymentMethod->getShippingAddressEquality()) {
            $data['availabilityRule']['conditions'][] = [
                'id' => md5('customerDifferentAddresses=' . $methodCode),
                'type' => 'customerDifferentAddresses',
                'value' => [
                    'isDifferent' => false,
                ],
            ];
        }

        $currencyIds = $this->getCurrencyIdsByCodes($paymentMethodOptions->getCurrencies(), $context);
        if ($currencyIds) {
            $data['availabilityRule']['conditions'][] = [
                'id' => md5('currency=' . $methodCode),
                'type' => 'currency',
                'value' => [
                    'operator' => '=',
                    'currencyIds' => $currencyIds
                ]
            ];
        } else {
            $data['active'] = false;
        }
        $data['customFields'] = [
            PevrPayeverIntegration::CUSTOM_FIELD_VARIANT_ID => $paymentMethod->getVariantId(),
            PevrPayeverIntegration::CUSTOM_FIELD_METHOD_CODE => $paymentMethod->getPaymentMethod(),
            PevrPayeverIntegration::CUSTOM_FIELD_ACCEPT_FEE => $paymentMethod->getAcceptFee(),
            PevrPayeverIntegration::CUSTOM_FIELD_FIXED_FEE => $paymentMethod->getFixedFee(),
            PevrPayeverIntegration::CUSTOM_FIELD_VARIABLE_FEE => $paymentMethod->getVariableFee(),
        ];
        $isRedirectMethod = $paymentMethod->isRedirectMethod();
        if ($isRedirectMethod) {
            $data['customFields'][PevrPayeverIntegration::CUSTOM_FIELD_IS_REDIRECT_METHOD] = true;
        }
        $this->paymentMethodRepository->upsert([$data], $context);

        return $data['active'];
    }

    /**
     * @param string $code
     * @return string
     */
    private function addPayeverPrefix(string $code): string
    {
        return self::PAYEVER_PREFIX . $code;
    }

    /**
     * @param array $countries
     * @param Context $context
     * @return array
     */
    private function getCountryIdsByCodes(array $countries, Context $context): array
    {
        $countryCodes = [];
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('iso', $countries));
        $searchResult = $this->countryRepo->search($criteria, $context);
        $shopwareCountries = $searchResult->getElements();
        foreach ($shopwareCountries as $shopwareCountry) {
            if ($shopwareCountry instanceof CountryEntity) {
                $countryCodes[] = $shopwareCountry->getId();
            }
        }

        return $countryCodes;
    }

    /**
     * @param array $currencies
     * @param Context $context
     * @return array
     */
    private function getCurrencyIdsByCodes(array $currencies, Context $context): array
    {
        $currencyCodes = [];
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('isoCode', $currencies));
        $searchResult = $this->currencyRepo->search($criteria, $context);
        $shopwareCurrencies = $searchResult->getElements();
        foreach ($shopwareCurrencies as $shopwareCurrency) {
            if ($shopwareCurrency instanceof CurrencyEntity) {
                $currencyCodes[] = $shopwareCurrency->getId();
            }
        }

        return $currencyCodes;
    }

    /**
     * @param Context $context
     * @return array
     */
    public function getAllPaymentOptionIds(Context $context): array
    {
        $paymentOptionIds = [];
        $pluginId = $this->pluginIdProvider->getPluginIdByBaseClass(
            PevrPayeverIntegration::class,
            $context
        );
        $criteria = new Criteria();
        $filter = new EqualsFilter('pluginId', $pluginId);
        $criteria->addFilter($filter);
        $searchResult = $this->paymentMethodRepository->search($criteria, $context);
        $payeverMethods = $searchResult->getElements();
        foreach ($payeverMethods as $payeverMethod) {
            if ($payeverMethod instanceof PaymentMethodEntity) {
                $paymentOptionIds[] = $payeverMethod->getId();
            }
        }

        return $paymentOptionIds;
    }
}
