<?php

namespace Payever\PayeverPayments\tests\unit\Service\Setting;

use Payever\PayeverPayments\Service\Setting\SettingsService;
use Payever\PayeverPayments\Service\Setting\Exception\PayeverSettingsInvalidException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use PHPUnit\Framework\MockObject\MockObject;

class SettingsServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var SettingsService
     */
    private $service;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->systemConfigService = $this->getMockBuilder(SystemConfigService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->service = new SettingsService($this->systemConfigService);
    }

    public function testGetSettings()
    {
        $this->systemConfigService->expects($this->once())
            ->method('getDomain')
            ->willReturn([
                'PevrPayeverIntegration.config.clientId' => 'come_client_uuid',
                'PevrPayeverIntegration.config.clientSecret' => 'come_client_secret',
                'PevrPayeverIntegration.config.key' => 'value',
            ]);
        $this->assertNotEmpty($this->service->getSettings());
        $this->assertNotEmpty($this->service->getSettings());
        $this->service->resetCache();
    }

    public function testUpdateSettings()
    {
        $this->systemConfigService->expects($this->once())
            ->method('set');
        $this->service->updateSettings(['key' => 'value']);
    }
}
