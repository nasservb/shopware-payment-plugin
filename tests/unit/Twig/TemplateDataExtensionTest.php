<?php

namespace Payever\PayeverPayments\tests\unit\Twig;

use Payever\PayeverPayments\Service\Setting\PayeverSettingGeneralStruct;
use Payever\PayeverPayments\Service\Setting\SettingsService;
use Payever\PayeverPayments\Twig\TemplateDataExtension;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class TemplateDataExtensionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|RequestStack
     */
    private $requestStack;

    /**
     * @var MockObject|SettingsService
     */
    private $settingsService;

    /**
     * @var TemplateDataExtension
     */
    private $extension;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->requestStack = $this->getMockBuilder(RequestStack::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->settingsService = $this->getMockBuilder(SettingsService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->extension = new TemplateDataExtension(
            $this->requestStack,
            $this->settingsService
        );
    }

    public function testGetGlobals()
    {
        $globalConfigCarrier = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
            ->disableOriginalConstructor()
            ->getMock();
        $scopedConfigCarrier = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->settingsService->expects($this->any())
            ->method('getSettings')
            ->willReturnCallback(function ($key) use ($globalConfigCarrier, $scopedConfigCarrier) {
                if ($key) {
                    return $scopedConfigCarrier;
                }

                return $globalConfigCarrier;
            });
        $globalConfigCarrier->expects($this->once())
            ->method('getBusinessUuid')
            ->willReturn('some-global-business-uuid');
        $this->requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(
                $request = $this->getMockBuilder(Request::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $parameterBag = $request->attributes = $this->getMockBuilder(ParameterBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $parameterBag->expects($this->once())
            ->method('get')
            ->willReturn(
                $context = $this->getMockBuilder(SalesChannelContext::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $context->expects($this->once())
            ->method('getSalesChannel')
            ->willReturn(
                $salesChannelEntity = $this->getMockBuilder(SalesChannelEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $salesChannelEntity->expects($this->once())
            ->method('getId')
            ->willReturn('some-id');
        $scopedConfigCarrier->expects($this->once())
            ->method('getBusinessUuid')
            ->willReturn('some-business-uuid-for-sales-channel');
        $this->assertNotEmpty($this->extension->getGlobals());
    }

    public function testGetGlobalsCaseException()
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willThrowException(new \Exception());
        $this->assertNotEmpty($this->extension->getGlobals());
    }
}
