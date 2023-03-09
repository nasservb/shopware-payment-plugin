<?php

namespace Payever\PayeverPayments\tests\unit\Service\Helper;

use Payever\PayeverPayments\Service\Helper\ConfigHelper;
use Payever\PayeverPayments\Service\Setting\PayeverSettingGeneralStruct;
use Payever\PayeverPayments\Service\Setting\SettingsService;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class ConfigHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|SettingsService
     */
    private $settingsService;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var ConfigHelper
     */
    private $helper;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->settingsService = $this->getMockBuilder(SettingsService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->helper = new ConfigHelper($this->settingsService, $this->logger);
    }

    public function testIsProductsAndInventorySyncEnabled()
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $struct = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $struct->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        $this->assertTrue($this->helper->isProductsSyncEnabled());
    }

    public function testIsProductsAndInventoryOutwardSyncEnabled()
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $struct = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $struct->expects($this->once())
            ->method('isProductsOutwardSyncEnabled')
            ->willReturn(true);
        $this->assertTrue($this->helper->isProductsOutwardSyncEnabled());
    }

    public function testGetProductsAndInventoryExternalId()
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $struct = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $struct->expects($this->once())
            ->method('getProductsSyncExternalId')
            ->willReturn($externalId = 'some-external-id');
        $this->assertEquals($externalId, $this->helper->getProductsSyncExternalId());
    }

    public function testGetBusinessUuid()
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $struct = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $struct->expects($this->once())
            ->method('getBusinessUuid')
            ->willReturn($businessId = 'some-business-id');
        $this->assertEquals($businessId, $this->helper->getBusinessUuid());
    }

    public function testIsCronMode()
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $struct = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $struct->expects($this->once())
            ->method('getProductsSyncMode')
            ->willReturn(PayeverSettingGeneralStruct::SYNC_MODE_INSTANT);
        $this->assertFalse($this->helper->isCronMode());
    }

    public function testIsCronModeCaseThrowable()
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $struct = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $struct->expects($this->once())
            ->method('getProductsSyncMode')
            ->willThrowException(new \Exception());
        $this->assertFalse($this->helper->isCronMode());
    }
}
