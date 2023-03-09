<?php

namespace Payever\PayeverPayments\tests\unit\Service\PayeverApi\Plugins;

use Payever\PayeverPayments\Service\PayeverApi\Plugins\PluginRegistryInfoProvider;
use Payever\PayeverPayments\Service\Setting\PayeverSettingGeneralStruct;
use Payever\PayeverPayments\Service\Setting\SettingsServiceInterface;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginService;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\Routing\RouterInterface;

class PluginRegistryInfoProviderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var PluginService
     */
    private $pluginService;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var SettingsServiceInterface
     */
    private $settingsService;

    /**
     * @var UriSigner
     */
    private $uriSigner;

    /**
     * @var PluginRegistryInfoProvider
     */
    private $provider;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->pluginService = $this->getMockBuilder(PluginService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->router = $this->getMockBuilder(RouterInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->settingsService = $this->getMockBuilder(SettingsServiceInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->uriSigner = $this->getMockBuilder(UriSigner::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->provider = new PluginRegistryInfoProvider(
            $this->pluginService,
            $this->router,
            $this->settingsService,
            $this->uriSigner,
            '6.3.3.0'
        );
    }

    public function testGetPluginVersion()
    {
        $this->pluginService->expects($this->once())
            ->method('getPluginByName')
            ->willReturn(
                $plugin = $this->getMockBuilder(PluginEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $plugin->expects($this->once())
            ->method('getVersion')
            ->willReturn('1.0.0');
        $this->assertNotEmpty($this->provider->getPluginVersion());
    }

    public function testGetCmsVersion()
    {
        $this->assertNotEmpty($this->provider->getCmsVersion());
    }

    public function testGetHost()
    {
        $this->router->expects($this->once())
            ->method('generate')
            ->willReturn('http://some.domain/path');
        $this->assertNotEmpty($this->provider->getHost());
    }

    public function testGetChannel()
    {
        $this->assertNotEmpty($this->provider->getChannel());
    }

    public function testGetSupportedCommands()
    {
        $this->assertNotEmpty($this->provider->getSupportedCommands());
    }

    public function testGetCommandEndpoint()
    {
        $this->router->expects($this->once())
            ->method('generate')
            ->willReturn($url = 'http://some.domain/path');
        $this->uriSigner->expects($this->once())
            ->method('sign')
            ->willReturn($url);
        $this->assertNotEmpty($this->provider->getCommandEndpoint());
    }

    public function testGetBusinessIds()
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $settings = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $settings->expects($this->once())
            ->method('getBusinessUuid')
            ->willReturn('some-uuid');
        $this->assertNotEmpty($this->provider->getBusinessIds());
    }

    public function testGetBusinessIdsCaseException()
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willThrowException(new \Exception());
        $this->assertNotEmpty($this->provider->getBusinessIds());
    }
}
