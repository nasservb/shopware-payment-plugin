<?php

namespace Payever\PayeverPayments\tests\unit\Service\PayeverApi;

use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use Payever\PayeverPayments\Service\PayeverApi\Core\Authorization\TokenList;
use Payever\PayeverPayments\Service\PayeverApi\Plugins\PluginRegistryInfoProvider;
use Payever\PayeverPayments\Service\Setting\PayeverSettingGeneralStruct;
use Payever\PayeverPayments\Service\Setting\SettingsServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class ClientFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|SettingsServiceInterface
     */
    private $settingsService;

    /**
     * @var MockObject|PluginRegistryInfoProvider
     */
    private $registryInfoProvider;

    /**
     * @var MockObject|TokenList
     */
    private $tokenList;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var ClientFactory
     */
    private $factory;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->settingsService = $this->getMockBuilder(SettingsServiceInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->registryInfoProvider = $this->getMockBuilder(PluginRegistryInfoProvider::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->tokenList = $this->getMockBuilder(TokenList::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->factory = new ClientFactory(
            $this->settingsService,
            $this->registryInfoProvider,
            $this->tokenList,
            $this->logger
        );
    }

    public function testGetPaymentsApiClient()
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $settings = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $settings->expects($this->once())
            ->method('isSandbox')
            ->willReturn(true);
        $settings->expects($this->once())
            ->method('getSandboxUrl')
            ->willReturn('http://some.domain1');
        $settings->expects($this->once())
            ->method('getLiveUrl')
            ->willReturn('http://some.domain2');
        $this->assertNotEmpty($this->factory->getPaymentsApiClient());
    }

    public function testGetPluginsApiClient()
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $settings = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $settings->expects($this->once())
            ->method('isSandbox')
            ->willReturn(true);
        $settings->expects($this->once())
            ->method('getSandboxUrl')
            ->willReturn('http://some.domain1');
        $settings->expects($this->once())
            ->method('getLiveUrl')
            ->willReturn('http://some.domain2');
        $this->assertNotEmpty($this->factory->getPluginsApiClient());
    }

    public function testGetThirdPartyApiClient()
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $settings = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $settings->expects($this->once())
            ->method('isSandbox')
            ->willReturn(true);
        $settings->expects($this->once())
            ->method('getThirdPartyProductsSandboxUrl')
            ->willReturn('http://some.domain1');
        $settings->expects($this->once())
            ->method('getThirdPartyProductsLiveUrl')
            ->willReturn('http://some.domain2');
        $this->assertNotEmpty($this->factory->getThirdPartyApiClient());
    }

    public function testGetProductsApiClient()
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $settings = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $settings->expects($this->once())
            ->method('isSandbox')
            ->willReturn(true);
        $settings->expects($this->once())
            ->method('getThirdPartyProductsSandboxUrl')
            ->willReturn('http://some.domain1');
        $settings->expects($this->once())
            ->method('getThirdPartyProductsLiveUrl')
            ->willReturn('http://some.domain2');
        $this->assertNotEmpty($this->factory->getProductsApiClient());
    }

    public function testGetInventoryApiClient()
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $settings = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $settings->expects($this->once())
            ->method('isSandbox')
            ->willReturn(true);
        $settings->expects($this->once())
            ->method('getThirdPartyProductsSandboxUrl')
            ->willReturn('http://some.domain1');
        $settings->expects($this->once())
            ->method('getThirdPartyProductsLiveUrl')
            ->willReturn('http://some.domain2');
        $this->assertNotEmpty($this->factory->getInventoryApiClient());
    }
}
