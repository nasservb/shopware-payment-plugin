<?php

namespace Payever\PayeverPayments\tests\unit\Service\Payment;

use Payever\ExternalIntegration\Core\Base\ResponseInterface;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\ConvertedPaymentOptionEntity;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\ListPaymentOptionsVariantsResultEntity;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\PaymentOptionOptionsEntity;
use Payever\ExternalIntegration\Payments\Http\ResponseEntity\ListPaymentOptionsWithVariantsResponse;
use Payever\ExternalIntegration\Payments\PaymentsApiClient;
use Payever\PayeverPayments\Service\Payment\PaymentOptionsService;
use Payever\PayeverPayments\Service\Setting\PayeverSettingGeneralStruct;
use Payever\PayeverPayments\Service\Setting\SettingsServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentMethodRepositoryDecorator;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Plugin\Util\PluginIdProvider;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class PaymentOptionsServiceTest extends \PHPUnit\Framework\TestCase
{
    /** @var MockObject|PluginIdProvider */
    private $pluginIdProvider;

    /** @var MockObject|PaymentMethodRepositoryDecorator */
    private $paymentMethodRepository;

    /** @var MockObject|PaymentsApiClient */
    private $paymentsApiClient;

    /** @var MockObject|EntityRepositoryInterface */
    private $countryRepo;

    /** @var MockObject|EntityRepositoryInterface */
    private $currencyRepo;

    /** @var MockObject|EntityRepositoryInterface */
    private $salesChannelRepo;

    /** @var MockObject|SettingsServiceInterface */
    private $settingsService;

    /** @var PaymentOptionsService */
    private $service;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->pluginIdProvider = $this->getMockBuilder(PluginIdProvider::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentMethodRepository = $this->getMockBuilder(PaymentMethodRepositoryDecorator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentsApiClient = $this->getMockBuilder(PaymentsApiClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->countryRepo = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->currencyRepo = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->salesChannelRepo = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->settingsService = $this->getMockBuilder(SettingsServiceInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->service = new PaymentOptionsService(
            $this->pluginIdProvider,
            $this->paymentMethodRepository,
            $this->paymentsApiClient,
            $this->countryRepo,
            $this->currencyRepo,
            $this->salesChannelRepo,
            $this->settingsService
        );
    }

    public function testDeactivateActivePaymentOptions()
    {
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
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
                'some-uuid' => 'some-code',
            ]);
        $this->service->deactivateActivePaymentOptions($context);
    }

    public function testSynchronizePaymentOptions()
    {
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->salesChannelRepo->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $salesChannel = $this->getMockBuilder(SalesChannelEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $salesChannel->expects($this->once())
            ->method('getId')
            ->willReturn('1');
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $config = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $config->expects($this->any())
            ->method('getBusinessUuid')
            ->willReturn('some-business-uuid');
        $this->paymentsApiClient->expects($this->once())
            ->method('listPaymentOptionsWithVariantsRequest')
            ->willReturn(
                $response = $this->getMockBuilder(ResponseInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $response->expects($this->once())
            ->method('getResponseEntity')
            ->willReturn(
                $responseEntity = $this->getMockBuilder(ListPaymentOptionsWithVariantsResponse::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getResult'])
                    ->getMock()
            );
        $responseEntity->expects($this->once())
            ->method('getResult')
            ->willReturn([
                $poWithVariant = $this->getMockBuilder(ListPaymentOptionsVariantsResultEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $poWithVariant->expects($this->once())
            ->method('toConvertedPaymentOptions')
            ->willReturn([
                $paymentMethod = $this->getMockBuilder(ConvertedPaymentOptionEntity::class)
                    ->disableOriginalConstructor()
                    ->addMethods([
                        'getOptions',
                        'getVariantName',
                        'getStatus',
                        'getPaymentMethod',
                    ])
                    ->getMock()
            ]);
        $paymentMethod->expects($this->once())
            ->method('getOptions')
            ->willReturn(
                $paymentMethodOptions = $this->getMockBuilder(PaymentOptionOptionsEntity::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getCurrencies', 'getCountries'])
                    ->getMock()
            );
        $paymentMethod->expects($this->any())
            ->method('getVariantName')
            ->willReturn('some_variant_name');
        $paymentMethod->expects($this->once())
            ->method('getStatus')
            ->willReturn(true);
        $paymentMethod->expects($this->any())
            ->method('getPaymentMethod')
            ->willReturn('payever_stripe');
        $paymentMethodOptions->expects($this->once())
            ->method('getCurrencies')
            ->willReturn(['EUR']);
        $paymentMethodOptions->expects($this->once())
            ->method('getCountries')
            ->willReturn(['DE']);
        $this->countryRepo->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $shopwareCountry = $this->getMockBuilder(CountryEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $shopwareCountry->expects($this->once())
            ->method('getId')
            ->willReturn('1');
        $this->currencyRepo->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $shopwareCurrency = $this->getMockBuilder(CurrencyEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $shopwareCurrency->expects($this->once())
            ->method('getId')
            ->willReturn('1');
        $this->service->synchronizePaymentOptions($context);
    }

    public function testSynchronizePaymentOptionsCaseException()
    {
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->salesChannelRepo->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('getElements')
            ->willReturn([]);

        $this->expectException(\UnexpectedValueException::class);
        $this->service->synchronizePaymentOptions($context);
    }

    public function testGetAllPaymentOptionIds()
    {
        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentMethodRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $payeverMethod = $this->getMockBuilder(PaymentMethodEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $payeverMethod->expects($this->once())
            ->method('getId')
            ->willReturn('1');
        $this->assertNotEmpty($this->service->getAllPaymentOptionIds($context));
    }
}
