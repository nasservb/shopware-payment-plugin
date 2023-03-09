<?php

namespace Payever\PayeverPayments\tests\unit\Service;

use Payever\ExternalIntegration\Core\Base\ResponseInterface;
use Payever\ExternalIntegration\Core\Lock\LockInterface;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\PaymentDetailsEntity;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\ExternalIntegration\Payments\Http\ResponseEntity\CreatePaymentV2Response;
use Payever\ExternalIntegration\Payments\Http\ResponseEntity\RetrievePaymentResponse;
use Payever\ExternalIntegration\Payments\PaymentsApiClient;
use Payever\ExternalIntegration\Plugins\Base\PluginRegistryInfoProviderInterface;
use Payever\PayeverPayments\PevrPayeverIntegration;
use Payever\PayeverPayments\Service\Item\Calculator;
use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use Payever\PayeverPayments\Service\PayeverPayment;
use Payever\PayeverPayments\Service\Payment\HiddenMethodService;
use Payever\PayeverPayments\Service\Payment\TransactionStatusService;
use Payever\PayeverPayments\Service\Setting\Exception\PayeverSettingsInvalidException;
use Payever\PayeverPayments\Service\Setting\PayeverSettingGeneralStruct;
use Payever\PayeverPayments\Service\Setting\SettingsServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentFinalizeException;
use Shopware\Core\Checkout\Payment\Exception\AsyncPaymentProcessException;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\Salutation\SalutationEntity;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

class PayeverPaymentTest extends \PHPUnit\Framework\TestCase
{
    /** @var MockObject|ClientFactory */
    private $apiClientFactory;

    /** @var MockObject|TransactionStatusService */
    private $transactionStatusService;

    /** @var MockObject|RouterInterface */
    private $router;

    /** @var MockObject|SettingsServiceInterface */
    private $settingsService;

    /** @var MockObject|HiddenMethodService */
    private $hiddenMethodService;

    /** @var MockObject|LockInterface */
    protected $locker;

    /** @var MockObject|LoggerInterface */
    private $logger;

    /** @var MockObject|PluginRegistryInfoProviderInterface */
    private $pluginRegistryInfoProvider;

    /** @var PayeverPayment */
    private $paymentHandler;

    /** @var Calculator&MockObject|MockObject */
    private $calculator;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->apiClientFactory = $this->getMockBuilder(ClientFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->transactionStatusService = $this->getMockBuilder(TransactionStatusService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->router = $this->getMockBuilder(RouterInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->settingsService = $this->getMockBuilder(SettingsServiceInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->hiddenMethodService = $this->getMockBuilder(HiddenMethodService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->locker = $this->getMockBuilder(LockInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->pluginRegistryInfoProvider = $this->getMockBuilder(PluginRegistryInfoProviderInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->calculator = $this->getMockBuilder(Calculator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentHandler = new PayeverPayment(
            $this->apiClientFactory,
            $this->transactionStatusService,
            $this->router,
            $this->settingsService,
            $this->hiddenMethodService,
            $this->pluginRegistryInfoProvider,
            $this->locker,
            $this->logger,
            $this->calculator
        );
    }

    public function testPay()
    {
        /** @var MockObject|AsyncPaymentTransactionStruct $transaction */
        $transaction = $this->getMockBuilder(AsyncPaymentTransactionStruct::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|RequestDataBag $dataBag */
        $dataBag = $this->getMockBuilder(RequestDataBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $salesChannelContext->expects($this->once())
            ->method('getSalesChannel')
            ->willReturn(
                $salesChannel = $this->getMockBuilder(SalesChannelEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $salesChannel->expects($this->once())
            ->method('getId')
            ->willReturn('1');
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $salesChannelContext->expects($this->once())
            ->method('getPaymentMethod')
            ->willReturn(
                $paymentMethod = $this->getMockBuilder(PaymentMethodEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentMethod->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([
                PevrPayeverIntegration::CUSTOM_FIELD_IS_REDIRECT_METHOD => false,
                PevrPayeverIntegration::CUSTOM_FIELD_METHOD_CODE => 'payever_stripe',
            ]);
        $this->apiClientFactory->expects($this->once())
            ->method('getPaymentsApiClient')
            ->willReturn(
                $paymentsApiClient = $this->getMockBuilder(PaymentsApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $transaction->expects($this->any())
            ->method('getOrder')
            ->willReturn(
                $order = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $order->expects($this->once())
            ->method('getAmountTotal')
            ->willReturn(1.1);
        $order->expects($this->once())
            ->method('getShippingTotal')
            ->willReturn(0.1);
        $transaction->expects($this->any())
            ->method('getOrderTransaction')
            ->willReturn(
                $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderTransaction->expects($this->any())
            ->method('getId')
            ->willReturn('1');
        $salesChannelContext->expects($this->once())
            ->method('getCurrency')
            ->willReturn(
                $currency = $this->getMockBuilder(CurrencyEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $currency->expects($this->once())
            ->method('getIsoCode')
            ->willReturn('EUR');
        $order->expects($this->once())
            ->method('getLineItems')
            ->willReturn($lineItems = new OrderLineItemCollection());
        $item = $this->getMockBuilder(OrderLineItemEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $item->expects($this->once())
            ->method('getUniqueIdentifier')
            ->willReturn('1');
        $lineItems->add($item);
        $item->expects($this->once())
            ->method('getPrice')
            ->willReturn(
                $price = $this->getMockBuilder(CalculatedPrice::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $price->expects($this->once())
            ->method('getCalculatedTaxes')
            ->willReturn(
                $taxes = $this->getMockBuilder(CalculatedTaxCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $taxes->expects($this->once())
            ->method('first')
            ->willReturn(
                $this->getMockBuilder(CalculatedTax::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $taxes->expects($this->once())
            ->method('getAmount')
            ->willReturn(0.19);
        $item->expects($this->any())
            ->method('getPayload')
            ->willReturn(['productNumber' => 'some-sku']);
        $price->expects($this->once())
            ->method('getUnitPrice')
            ->willReturn(1.19);
        $salesChannelContext->expects($this->once())
            ->method('getCustomer')
            ->willReturn(
                $customer = $this->getMockBuilder(CustomerEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $customer->expects($this->once())
            ->method('getBirthday')
            ->willReturn(new \DateTime());
        $customer->expects($this->once())
            ->method('getDefaultBillingAddress')
            ->willReturn(
                $billingAddress = $this->getMockBuilder(CustomerAddressEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

        $customer->expects($this->any())
                 ->method('getSalutation')
                 ->willReturn(
                     $salutationEntity = $this->getMockBuilder(SalutationEntity::class)
                         ->disableOriginalConstructor()
                         ->getMock()
                 );

        $billingAddress->expects($this->any())
            ->method('getCountry')
            ->willReturn(
                $countryEntity = $this->getMockBuilder(CountryEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $countryEntity->expects($this->once())
            ->method('getIso')
            ->willReturn('DE');

        $customer->expects($this->once())
            ->method('getActiveShippingAddress')
            ->willReturn(
                $shippingAddress = $this->getMockBuilder(CustomerAddressEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

        $shippingAddress->expects($this->any())
            ->method('getSalutation')
            ->willReturn(
                $salutationEntity = $this->getMockBuilder(SalutationEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );

        $shippingAddress->expects($this->any())
            ->method('getCountry')
            ->willReturn(
                $countryEntity = $this->getMockBuilder(CountryEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $countryEntity->expects($this->once())
            ->method('getIso')
            ->willReturn('DE');

        $transaction->expects($this->any())
            ->method('getReturnUrl')
            ->willReturn($baseUrl = 'http://some.domain/path');
        $this->router->expects($this->any())
            ->method('generate')
            ->willReturn($baseUrl);
        $paymentsApiClient->expects($this->once())
            ->method('createPaymentV2Request')
            ->willReturn(
                $response = $this->getMockBuilder(ResponseInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $response->expects($this->once())
            ->method('getResponseEntity')
            ->willReturn(
                $responseEntity = $this->getMockBuilder(CreatePaymentV2Response::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getRedirectUrl'])
                    ->getMock()
            );
        $responseEntity->expects($this->once())
            ->method('getRedirectUrl')
            ->willReturn($baseUrl);
        $this->paymentHandler->pay($transaction, $dataBag, $salesChannelContext);
    }

    public function testPayCaseException()
    {
        /** @var MockObject|AsyncPaymentTransactionStruct $transaction */
        $transaction = $this->getMockBuilder(AsyncPaymentTransactionStruct::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|RequestDataBag $dataBag */
        $dataBag = $this->getMockBuilder(RequestDataBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $salesChannelContext->expects($this->once())
            ->method('getSalesChannel')
            ->willReturn(
                $salesChannel = $this->getMockBuilder(SalesChannelEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $salesChannel->expects($this->once())
            ->method('getId')
            ->willReturn('1');
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willThrowException(new PayeverSettingsInvalidException('some-message'));
        $this->expectException(AsyncPaymentProcessException::class);
        $this->paymentHandler->pay($transaction, $dataBag, $salesChannelContext);
    }

    public function testFinalize()
    {
        /** @var MockObject|AsyncPaymentTransactionStruct $transaction */
        $transaction = $this->getMockBuilder(AsyncPaymentTransactionStruct::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $parameterBag = $request->query = $this->getMockBuilder(ParameterBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $parameterBag->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) {
                if (PayeverPayment::REQUEST_PARAMETER_PAYMENT_ID === $key) {
                    return 'some-payment-uuid';
                }
                if ('type' === $key) {
                    return 'success';
                }
            });
        $salesChannelContext->expects($this->once())
            ->method('getSalesChannel')
            ->willReturn(
                $salesChannel = $this->getMockBuilder(SalesChannelEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $salesChannel->expects($this->once())
            ->method('getId')
            ->willReturn('1');
        $this->apiClientFactory->expects($this->once())
            ->method('getPaymentsApiClient')
            ->willReturn(
                $paymentsApiClient = $this->getMockBuilder(PaymentsApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentsApiClient->expects($this->once())
            ->method('retrievePaymentRequest')
            ->willReturn(
                $response = $this->getMockBuilder(ResponseInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $response->expects($this->once())
            ->method('getResponseEntity')
            ->willReturn(
                $responseEntity = $this->getMockBuilder(RetrievePaymentResponse::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getResult'])
                    ->getMock()
            );
        $responseEntity->expects($this->once())
            ->method('getResult')
            ->willReturn(
                $this->getMockBuilder(RetrievePaymentResultEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->paymentHandler->finalize($transaction, $request, $salesChannelContext);
    }

    public function testFinalizeCaseFinish()
    {
        /** @var MockObject|AsyncPaymentTransactionStruct $transaction */
        $transaction = $this->getMockBuilder(AsyncPaymentTransactionStruct::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $parameterBag = $request->query = $this->getMockBuilder(ParameterBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $parameterBag->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) {
                if (PayeverPayment::REQUEST_PARAMETER_PAYMENT_ID === $key) {
                    return 'some-payment-uuid';
                }
                if ('type' === $key) {
                    return 'finish';
                }
            });
        $transaction->expects($this->once())
            ->method('getOrderTransaction')
            ->willReturn(
                $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderTransaction->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_ID => 'some-uuid']);
        $salesChannelContext->expects($this->once())
            ->method('getSalesChannel')
            ->willReturn(
                $salesChannel = $this->getMockBuilder(SalesChannelEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $salesChannel->expects($this->once())
            ->method('getId')
            ->willReturn('1');
        $this->apiClientFactory->expects($this->once())
            ->method('getPaymentsApiClient')
            ->willReturn(
                $paymentsApiClient = $this->getMockBuilder(PaymentsApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentsApiClient->expects($this->once())
            ->method('retrievePaymentRequest')
            ->willReturn(
                $response = $this->getMockBuilder(ResponseInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $response->expects($this->once())
            ->method('getResponseEntity')
            ->willReturn(
                $responseEntity = $this->getMockBuilder(RetrievePaymentResponse::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getResult'])
                    ->getMock()
            );
        $responseEntity->expects($this->once())
            ->method('getResult')
            ->willReturn(
                $this->getMockBuilder(RetrievePaymentResultEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->paymentHandler->finalize($transaction, $request, $salesChannelContext);
    }

    public function testFinalizeCaseFinishNoPaymentId()
    {
        /** @var MockObject|AsyncPaymentTransactionStruct $transaction */
        $transaction = $this->getMockBuilder(AsyncPaymentTransactionStruct::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $parameterBag = $request->query = $this->getMockBuilder(ParameterBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $parameterBag->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) {
                if ('type' === $key) {
                    return 'finish';
                }
            });
        $transaction->expects($this->any())
            ->method('getOrderTransaction')
            ->willReturn(
                $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderTransaction->expects($this->once())
            ->method('getCustomFields')
            ->willReturn(null);
        $this->expectException(AsyncPaymentFinalizeException::class);
        $this->paymentHandler->finalize($transaction, $request, $salesChannelContext);
    }

    public function testFinalizeCaseCancel()
    {
        /** @var MockObject|AsyncPaymentTransactionStruct $transaction */
        $transaction = $this->getMockBuilder(AsyncPaymentTransactionStruct::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $parameterBag = $request->query = $this->getMockBuilder(ParameterBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $parameterBag->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) {
                if (PayeverPayment::REQUEST_PARAMETER_PAYMENT_ID === $key) {
                    return 'some-payment-uuid';
                }
                if ('type' === $key) {
                    return 'cancel';
                }
            });
        $transaction->expects($this->once())
            ->method('getOrderTransaction')
            ->willReturn(
                $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderTransaction->expects($this->once())
            ->method('getId')
            ->willReturn('1');
        $this->expectException(CustomerCanceledAsyncPaymentException::class);
        $this->paymentHandler->finalize($transaction, $request, $salesChannelContext);
    }

    public function testFinalizeCaseFailure()
    {
        /** @var MockObject|AsyncPaymentTransactionStruct $transaction */
        $transaction = $this->getMockBuilder(AsyncPaymentTransactionStruct::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|SalesChannelContext $salesChannelContext */
        $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $parameterBag = $request->query = $this->getMockBuilder(ParameterBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $parameterBag->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) {
                if (PayeverPayment::REQUEST_PARAMETER_PAYMENT_ID === $key) {
                    return 'some-payment-uuid';
                }
                if ('type' === $key) {
                    return 'failure';
                }
            });
        $salesChannelContext->expects($this->once())
            ->method('getSalesChannel')
            ->willReturn(
                $salesChannel = $this->getMockBuilder(SalesChannelEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $salesChannel->expects($this->once())
            ->method('getId')
            ->willReturn('1');
        $this->apiClientFactory->expects($this->once())
            ->method('getPaymentsApiClient')
            ->willReturn(
                $paymentsApiClient = $this->getMockBuilder(PaymentsApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentsApiClient->expects($this->once())
            ->method('retrievePaymentRequest')
            ->willReturn(
                $response = $this->getMockBuilder(ResponseInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $response->expects($this->once())
            ->method('getResponseEntity')
            ->willReturn(
                $responseEntity = $this->getMockBuilder(RetrievePaymentResponse::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getResult'])
                    ->getMock()
            );
        $responseEntity->expects($this->once())
            ->method('getResult')
            ->willReturn(
                $result = $this->getMockBuilder(RetrievePaymentResultEntity::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getPaymentType'])
                    ->getMock()
            );
        $result->expects($this->once())
            ->method('getPaymentType')
            ->willReturn('some_payment_type');
        $transaction->expects($this->once())
            ->method('getOrderTransaction')
            ->willReturn(
                $orderTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderTransaction->expects($this->once())
            ->method('getId')
            ->willReturn('1');
        $this->expectException(AsyncPaymentFinalizeException::class);
        $this->paymentHandler->finalize($transaction, $request, $salesChannelContext);
    }

    public function testGetPayeverPaymentCode()
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $settings = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $settings->expects($this->once())
            ->method('getActivePayeverMethods')
            ->willReturn([
                $uniqueId = 'some_id' => $methodCode = 'payever_stripe',
            ]);
        $this->assertEquals($methodCode, $this->paymentHandler->getPayeverPaymentCode($uniqueId));
    }

    public function testGenerateIframeUrl()
    {
        $reflection = new \Reflectionclass($this->paymentHandler);
        $method = $reflection->getMethod('generateIframeUrl');
        $method->setAccessible(true);
        $this->router->expects($this->once())
            ->method('generate')
            ->willReturn('some-url');
        $this->assertNotEmpty($method->invoke($this->paymentHandler, 'some-url'));
    }
}
