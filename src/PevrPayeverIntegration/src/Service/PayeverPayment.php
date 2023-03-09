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

namespace Payever\PayeverPayments\Service;

use Payever\ExternalIntegration\Core\Http\RequestEntity;
use Payever\ExternalIntegration\Core\Lock\LockInterface;
use Payever\ExternalIntegration\Core\Enum\ChannelSet;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\ChannelEntity;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\CustomerAddressEntity as AddressEntity;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\PaymentDataEntity;
use Payever\ExternalIntegration\Payments\Http\RequestEntity\CreatePaymentV2Request;
use Payever\ExternalIntegration\Payments\Http\ResponseEntity\CreatePaymentV2Response;
use Payever\ExternalIntegration\Payments\Http\ResponseEntity\RetrievePaymentResponse;
use Payever\ExternalIntegration\Plugins\Base\PluginRegistryInfoProviderInterface;
use Payever\PayeverPayments\PevrPayeverIntegration;
use Payever\PayeverPayments\Service\Item\Calculator;
use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use Payever\PayeverPayments\Service\Payment\HiddenMethodService;
use Payever\PayeverPayments\Service\Payment\TransactionStatusService;
use Payever\PayeverPayments\Service\Setting\SettingsServiceInterface;
use Payever\PayeverPayments\OrderItems\OrderItemsEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class PayeverPayment implements AsynchronousPaymentHandlerInterface
{
    public const REQUEST_PARAMETER_PAYMENT_ID = 'paymentId';
    public const REQUEST_PARAMETER_TYPE = 'type';
    public const REQUEST_PARAMETER_TRANSACTION_ID = 'transactionId';

    private const ROUTE_PAYMENT_NOTIFICATION = 'payever.payment.notification';
    private const ROUTE_PAYMENT_CANCEL = 'payever.payment.cancel';
    private const ROUTE_PAYMENT_IFRAME = 'payever.payment.iframe';
    private const ROUTE_PAYMENT_CUSTOM_REDIRECT = 'payever.payment.custom';
    private const ROUTE_PAYMENT_SUCCESS = 'payever.payment.success';

    public const CALLBACK_TYPE_SUCCESS = 'success';
    private const CALLBACK_TYPE_FINISH = 'finish';
    private const CALLBACK_TYPE_CANCEL = 'cancel';
    private const CALLBACK_TYPE_FAILURE = 'failure';
    private const CALLBACK_TYPE_PENDING = 'pending';

    private const MR_SALUTATION = 'mr';
    private const MRS_SALUTATION = 'mrs';
    private const MS_SALUTATION = 'ms';

    public const LOCK_TIMEOUT = 30;

    private const MAJORITY_YEARS = 18;

    /** @var ClientFactory */
    private $apiClientFactory;

    /** @var TransactionStatusService */
    private $transactionStatusService;

    /** @var RouterInterface */
    private $router;

    /** @var SettingsServiceInterface */
    private $settingsService;

    /** @var HiddenMethodService */
    private $hiddenMethodService;

    /** @var LockInterface */
    protected $locker;

    /** @var LoggerInterface */
    private $logger;

    /** @var PluginRegistryInfoProviderInterface */
    private $pluginRegistryInfoProvider;

    /** @var array */
    private $paymentResultList = [];

    /** @var Calculator  */
    private $calculator;

    /**
     * @param ClientFactory $apiClientFactory
     * @param TransactionStatusService $transactionStatusService
     * @param RouterInterface $router
     * @param SettingsServiceInterface $settingsService
     * @param HiddenMethodService $hiddenMethodService
     * @param PluginRegistryInfoProviderInterface $pluginRegistryInfoProvider
     * @param LockInterface $locker
     * @param LoggerInterface $logger
     * @param Calculator $calculator
     */
    public function __construct(
        ClientFactory $apiClientFactory,
        TransactionStatusService $transactionStatusService,
        RouterInterface $router,
        SettingsServiceInterface $settingsService,
        HiddenMethodService $hiddenMethodService,
        PluginRegistryInfoProviderInterface $pluginRegistryInfoProvider,
        LockInterface $locker,
        LoggerInterface $logger,
        Calculator $calculator
    ) {
        $this->apiClientFactory = $apiClientFactory;
        $this->transactionStatusService = $transactionStatusService;
        $this->router = $router;
        $this->settingsService = $settingsService;
        $this->hiddenMethodService = $hiddenMethodService;
        $this->pluginRegistryInfoProvider = $pluginRegistryInfoProvider;
        $this->locker = $locker;
        $this->logger = $logger;
        $this->calculator = $calculator;
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param RequestDataBag $dataBag
     * @param SalesChannelContext $salesChannelContext
     * @return RedirectResponse
     * @throws AsyncPaymentProcessException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function pay(
        AsyncPaymentTransactionStruct $transaction,
        RequestDataBag $dataBag,
        SalesChannelContext $salesChannelContext
    ): RedirectResponse {
        try {
            $this->logger->info(
                sprintf(
                    'Starting payment process for order %s',
                    $transaction->getOrder()->getAutoIncrement()
                )
            );

            $redirectUrl = $this->getRedirectUrl($transaction, $salesChannelContext);
        } catch (\Exception $e) {
            $this->logger->critical(
                sprintf(
                    'Create payment failed for order %s: %s',
                    $transaction->getOrder()->getAutoIncrement(),
                    $e->getMessage()
                )
            );

            throw new AsyncPaymentProcessException(
                $transaction->getOrderTransaction()->getId(),
                'An error occurred during the communication with external payment gateway'
                    . PHP_EOL . $e->getMessage()
            );
        }

        return new RedirectResponse($redirectUrl);
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param Request $request
     * @param SalesChannelContext $salesChannelContext
     *
     * @throws \Exception
     */
    public function finalize(
        AsyncPaymentTransactionStruct $transaction,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): void {
        $paymentId = $request->query->get(static::REQUEST_PARAMETER_PAYMENT_ID, '');
        $callbackType = $request->query->get(static::REQUEST_PARAMETER_TYPE);

        $this->logger->info(sprintf('Handling callback %s for payment %s', $callbackType, $paymentId));
        if (self::CALLBACK_TYPE_FINISH === $callbackType) {
            $customFields = $transaction->getOrderTransaction()->getCustomFields();
            $paymentId = $customFields[PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_ID] ?? null;
            if (!$paymentId) {
                throw new AsyncPaymentFinalizeException(
                    $transaction->getOrderTransaction()->getId(),
                    'Unable to retrieve payment_id'
                );
            }
        }

        switch ($callbackType) {
            case self::CALLBACK_TYPE_FINISH:
            case self::CALLBACK_TYPE_SUCCESS:
            case self::CALLBACK_TYPE_PENDING:
                $this->logger->debug(sprintf('Attempt to lock %s', $paymentId));
                $this->locker->acquireLock($paymentId, self::LOCK_TIMEOUT);
                $payeverPayment = $this->retrieveRequest($paymentId, $salesChannelContext);

                if ($payeverPayment->getTotal() != $transaction->getOrder()->getAmountTotal()) {
                    throw new \UnexpectedValueException('Transaction amount didn\'t match order amount.');
                }

                $this->transactionStatusService->persistTransactionStatus($salesChannelContext, $payeverPayment);
                $this->transactionStatusService->allocateOrderTotals(
                    $transaction->getOrder()->getId(),
                    $payeverPayment
                );

                $this->locker->releaseLock($paymentId);
                $this->logger->debug(sprintf('Unlocked  %s', $paymentId));
                break;
            case self::CALLBACK_TYPE_CANCEL:
                throw new CustomerCanceledAsyncPaymentException(
                    $transaction->getOrderTransaction()->getId(),
                    'Customer canceled the payment on the payever page'
                );
            case self::CALLBACK_TYPE_FAILURE:
                $payeverPayment = $this->retrieveRequest($paymentId, $salesChannelContext);
                $this->transactionStatusService->persistTransactionStatus($salesChannelContext, $payeverPayment, false);
                $this->hiddenMethodService
                    ->processFailedMethodByCode($payeverPayment->getPaymentType(), $transaction->getOrder()->getId());

                throw new AsyncPaymentFinalizeException(
                    $transaction->getOrderTransaction()->getId(),
                    'Please change payment method'
                );
        }
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param SalesChannelContext $salesChannelContext
     *
     * @return string
     *
     * @throws \Exception
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    private function getRedirectUrl(
        AsyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext
    ): string {
        $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
        $settings = $this->settingsService->getSettings($salesChannelId);
        $paymentMethod = $salesChannelContext->getPaymentMethod();
        $methodCustomFields = $paymentMethod->getCustomFields();
        $isRedirectMethod = isset($methodCustomFields[PevrPayeverIntegration::CUSTOM_FIELD_IS_REDIRECT_METHOD])
            && $methodCustomFields[PevrPayeverIntegration::CUSTOM_FIELD_IS_REDIRECT_METHOD];
        $isRedirectMethod = $isRedirectMethod && $settings->isForceRedirect();
        $methodCode = $methodCustomFields[PevrPayeverIntegration::CUSTOM_FIELD_METHOD_CODE]
            ?? $this->getPayeverPaymentCode($paymentMethod->getId());
        $variantId = $methodCustomFields[PevrPayeverIntegration::CUSTOM_FIELD_VARIANT_ID] ?? null;
        $paymentsApiClient = $this->apiClientFactory
            ->getPaymentsApiClient($salesChannelId);

        $paymentRequestEntity = $this->getCreatePaymentV2RequestEntity(
            $transaction,
            $salesChannelContext,
            $methodCode,
            $variantId
        );

        $language = $settings->getCheckoutLanguage();
        if ($language) {
            $paymentRequestEntity->setLocale($language);
        }

        // Set `force_redirect` flag in the payment data
        $paymentData = $paymentRequestEntity->getPaymentData();
        if (!$paymentData) {
            $paymentData = new PaymentDataEntity();
        }

        $paymentData->setForceRedirect((bool) $isRedirectMethod);
        $paymentRequestEntity->setPaymentData($paymentData);

        $response = $paymentsApiClient->createPaymentV2Request($paymentRequestEntity);
        /** @var CreatePaymentV2Response $responseEntity */
        $responseEntity = $response->getResponseEntity();
        $redirectUrl = $responseEntity->getRedirectUrl();

        if (!$redirectUrl) {
            $reason = $responseEntity->getErrorDescription() ?? 'redirect_url is empty';
            throw new \UnexpectedValueException(sprintf('Create payment API error: %s', $reason));
        }

        return !$isRedirectMethod && $settings->isIframe()
            ? $this->generateIframeUrl($redirectUrl)
            : $redirectUrl;
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param SalesChannelContext $salesChannelContext
     * @param string $methodCode
     * @param string|null $variantId
     * @return RequestEntity|CreatePaymentV2Request
     */
    private function getCreatePaymentV2RequestEntity(
        AsyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext,
        string $methodCode,
        ?string $variantId = null
    ): RequestEntity {
        return $this->populatePaymentRequestEntity(
            $transaction,
            $salesChannelContext,
            new CreatePaymentV2Request(),
            $methodCode,
            $variantId
        );
    }

    /**
     * @param AsyncPaymentTransactionStruct $transaction
     * @param SalesChannelContext $salesChannelContext
     * @param RequestEntity|CreatePaymentV2Request $requestEntity
     * @param string $methodCode
     * @param string|null $variantId
     * @return RequestEntity|CreatePaymentV2Request
     * @throws \UnexpectedValueException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    private function populatePaymentRequestEntity(
        AsyncPaymentTransactionStruct $transaction,
        SalesChannelContext $salesChannelContext,
        RequestEntity $requestEntity,
        string $methodCode,
        ?string $variantId = null
    ): RequestEntity {
        $order = $transaction->getOrder();
        $requestEntity
            ->setAmount($order->getAmountTotal())
            ->setFee($order->getShippingTotal())
            ->setOrderId((string) $order->getOrderNumber())
            ->setCurrency($salesChannelContext->getCurrency()->getIsoCode())
            ->setPaymentMethod($methodCode)
            ->setVariantId($variantId)
            ->setCart($this->collectCartInfo($order->getLineItems()))
            ->setPaymentData(new PaymentDataEntity());

        $customer = $salesChannelContext->getCustomer();
        if (!$customer) {
            throw new \UnexpectedValueException('Unable to load customer');
        }

        $birthday = $customer->getBirthday();
        if (null !== $birthday && $birthday->diff(new \DateTime())->y >= self::MAJORITY_YEARS) {
            $requestEntity->setBirthdate($birthday->format('Y-m-d'));
        }
        $billingAddress = $customer->getDefaultBillingAddress();
        if (!$billingAddress) {
            throw new \UnexpectedValueException('Unable to load default billing address');
        }

        $requestEntity
            ->setEmail($customer->getEmail())
            ->setPhone($billingAddress->getPhoneNumber());

        $channelEntity = new ChannelEntity();
        $channelEntity
            ->setName(ChannelSet::CHANNEL_SHOPWARE)
            ->setSource($this->pluginRegistryInfoProvider->getCmsVersion())
            ->setType('ecommerce');
        $requestEntity->setChannel($channelEntity)
                      ->setPluginVersion($this->pluginRegistryInfoProvider->getPluginVersion())
                      ->setBillingAddress($this->populateAddressEntity($billingAddress));

        $shippingAddress = $customer->getActiveShippingAddress();
        $requestEntity->setShippingAddress($this->populateAddressEntity($shippingAddress));
        $returnUrl = $transaction->getReturnUrl();
        $requestEntity
            ->setSuccessUrl($this->generateSuccessRedirectUrl($transaction->getOrderTransaction()->getId()))
            ->setFailureUrl($this->generateCallbackUrl($returnUrl, self::CALLBACK_TYPE_FAILURE))
            ->setCancelUrl($this->generateCancelUrl($transaction->getOrderTransaction()->getId()))
            ->setNoticeUrl($this->generateNoticeUrl($transaction->getOrderTransaction()->getId()))
            ->setPendingUrl($this->generateCallbackUrl($returnUrl, self::CALLBACK_TYPE_PENDING));

        $company = $billingAddress->getCompany();
        if (!empty($company)) {
            $paymentData = $requestEntity->getPaymentData();
            if (!$paymentData) {
                $paymentData = new PaymentDataEntity();
            }

            $paymentData->setOrganizationName($company);

            $requestEntity->setPaymentData($paymentData);
        }

        return $requestEntity;
    }

    /**
     * @param CustomerAddressEntity $address
     *
     * @return AddressEntity
     */
    private function populateAddressEntity($address)
    {
        $addressEntity = new AddressEntity();
        $countryState = $address->getCountryState();
        $addressEntity
            ->setFirstName($address->getFirstName())
            ->setLastName($address->getLastName())
            ->setCity($address->getCity())
            ->setRegion($countryState ? $countryState->getName() : '')
            ->setZip($address->getZipcode())
            ->setStreet($address->getStreet())
            ->setCountry(
                $address->getCountry()
                    ? $address->getCountry()->getIso()
                    : null
            );

        $salutation = $this->getValidSalutation(
            $address->getSalutation()
                ? $address->getSalutation()->getSalutationKey()
                : null
        );

        if ($salutation) {
            $addressEntity->setSalutation($salutation);
        }

        return $addressEntity;
    }

    /**
     * Validates and returns salutation
     *
     * @param string|null $salutation
     * @return bool|string
     */
    private function getValidSalutation(string $salutation = null)
    {
        if (!$salutation) {
            return false;
        }

        $salutation = strtolower($salutation);
        return ($salutation == self::MR_SALUTATION
            || $salutation == self::MRS_SALUTATION
            || $salutation == self::MS_SALUTATION) ? $salutation : false;
    }

    /**
     * @param string $uniqueId
     *
     * @return string
     *
     * @throws Setting\Exception\PayeverSettingsInvalidException
     */
    public function getPayeverPaymentCode(string $uniqueId): string
    {
        $activeMethods = $this->settingsService->getSettings()->getActivePayeverMethods();

        return $activeMethods[$uniqueId];
    }

    /**
     * @param string $returnUrl
     * @param string $type
     * @param bool $appendPlaceholders
     * @return string
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    private function generateCallbackUrl(string $returnUrl, string $type, bool $appendPlaceholders = true): string
    {
        $params = ['type' => $type];

        if ($appendPlaceholders && $type !== self::CALLBACK_TYPE_CANCEL) {
            $params['paymentId'] = '--PAYMENT-ID--';
        }

        return sprintf('%s&%s', $returnUrl, http_build_query($params));
    }

    /**
     * @param $orderTransactionId
     * @return string
     */
    private function generateNoticeUrl($orderTransactionId): string
    {
        return $this->router->generate(
            self::ROUTE_PAYMENT_NOTIFICATION,
            [
                static::REQUEST_PARAMETER_PAYMENT_ID => '--PAYMENT-ID--',
                static::REQUEST_PARAMETER_TRANSACTION_ID => $orderTransactionId],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    /**
     * @param string $orderTransactionId
     * @return string
     */
    private function generateCancelUrl($orderTransactionId): string
    {
        return $this->router->generate(
            self::ROUTE_PAYMENT_CANCEL,
            [static::REQUEST_PARAMETER_TRANSACTION_ID => $orderTransactionId],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    /**
     * @param string $returnUrl
     * @return string
     */
    private function generateIframeUrl(string $returnUrl): string
    {
        return $this->router->generate(
            self::ROUTE_PAYMENT_IFRAME,
            ['returnUrl' => $returnUrl],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    /**
     * @param string $orderTransactionId
     * @return string
     */
    private function generateSuccessRedirectUrl($orderTransactionId): string
    {
        return $this->router->generate(
            self::ROUTE_PAYMENT_SUCCESS,
            [
                static::REQUEST_PARAMETER_PAYMENT_ID => '--PAYMENT-ID--',
                static::REQUEST_PARAMETER_TRANSACTION_ID => $orderTransactionId
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    /**
     * @param OrderLineItemCollection $items
     * @return string|null
     */
    private function collectCartInfo(OrderLineItemCollection $items): ?string
    {
        $cart = [];
        foreach ($items as $item) {
            /** @var OrderItemsEntity $item */
            $price = $item->getPrice();
            if (!$price) {
                throw new \UnexpectedValueException('Unable to load price');
            }
            $taxes = $price->getCalculatedTaxes();
            $tax = $taxes->first();
            $taxAmount = $taxes->getAmount();

            $sku = (is_array($item->getPayload()) && array_key_exists('productNumber', $item->getPayload())) ?
                $item->getPayload()['productNumber'] :
                $item->getLabel();

            $unitPrice = $price->getUnitPrice();
            if ($unitPrice < $taxAmount) {
                $taxAmount = $unitPrice * ($tax->getTaxRate() / 100);
            }

            $cart[] = [
                'name' => $item->getLabel(),
                'sku' => preg_replace('#[^0-9a-z_]+#i', '-', $sku),
                'price' => $this->calculator->calculateItemPrice($items, $item),
                'priceNetto' => $unitPrice - $taxAmount,
                'vatRate' => $tax ? $tax->getTaxRate() : null,
                'quantity' => $item->getQuantity(),
                'description' => $item->getDescription(),
                'identifier' => $item->getIdentifier(),
                // @todo Add product image
                'thumbnail' => '',
            ];
        }

        return json_encode($cart) ?? null;
    }

    /**
     * @param string $paymentId
     * @param SalesChannelContext $salesChannelContext
     *
     * @return RetrievePaymentResultEntity|null
     *
     * @throws \Exception
     */
    public function retrieveRequest(
        string $paymentId,
        SalesChannelContext $salesChannelContext
    ): ?RetrievePaymentResultEntity {
        $result = $this->paymentResultList[$paymentId] ?? null;
        if (null === $result) {
            $salesChannelId = $salesChannelContext->getSalesChannel()->getId();
            $response = $this->apiClientFactory->getPaymentsApiClient($salesChannelId)
                ->retrievePaymentRequest($paymentId);
            /** @var RetrievePaymentResponse $responseEntity */
            $responseEntity = $response->getResponseEntity();
            /** @var RetrievePaymentResultEntity $result */
            $result = $this->paymentResultList[$paymentId] = $responseEntity->getResult();
        }

        return $result;
    }
}
