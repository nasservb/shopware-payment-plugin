<?php

namespace Payever\PayeverPayments\tests\unit\Service\Setting;

use Payever\PayeverPayments\Service\Setting\Exception\PayeverSettingsInvalidException;
use Payever\PayeverPayments\Service\Setting\PayeverSettingGeneralStruct;

class PayeverSettingGeneralStructTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var PayeverSettingGeneralStruct
     */
    private $struct;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->struct = new PayeverSettingGeneralStruct();
    }

    public function testSetGetIsSandbox()
    {
        $this->struct->setSandbox($isSandbox = true);
        $this->assertEquals($isSandbox, $this->struct->isSandbox());
    }

    public function testSetGetClientId()
    {
        $this->struct->setClientId($clientId = 'some-client-id');
        $this->assertEquals($clientId, $this->struct->getClientId());
    }

    public function testSetGetClientSecret()
    {
        $this->struct->setClientSecret($clientSecret = 'some-client-secret');
        $this->assertEquals($clientSecret, $this->struct->getClientSecret());
    }

    public function testSetGetBusinessUuid()
    {
        $this->struct->setBusinessUuid($businessId = 'some-business-id');
        $this->assertEquals($businessId, $this->struct->getBusinessUuid());
    }

    public function testSetGetIsIframe()
    {
        $this->struct->setIsIframe($isFrame = true);
        $this->assertEquals($isFrame, $this->struct->isIframe());
    }

    public function testSetGetCheckoutLanguage()
    {
        $this->struct->setCheckoutLanguage($lang = 'en');
        $this->assertEquals($lang, $this->struct->getCheckoutLanguage());
    }

    public function testSetGetisForceRedirect()
    {
        $this->struct->setIsForceRedirect(true);
        $this->assertTrue($this->struct->isForceRedirect());
    }

    public function testSetGetSaveOrderOnError()
    {
        $this->struct->setSaveOrderOnError($flag = true);
        $this->assertEquals($flag, $this->struct->saveOrderOnError());
    }

    public function testSetGetSandboxUrl()
    {
        $this->struct->setSandboxUrl($url = 'http://some.sandox/url');
        $this->assertEquals($url, $this->struct->getSandboxUrl());
    }

    public function testSetGetLiveUrl()
    {
        $this->struct->setLiveUrl($url = 'http://some.live/url');
        $this->assertEquals($url, $this->struct->getLiveUrl());
    }

    public function testSetGetThirdPartyProductsSandboxUrl()
    {
        $this->struct->setThirdPartyProductsSandboxUrl($url = 'http://some.sandox/url');
        $this->assertEquals($url, $this->struct->getThirdPartyProductsSandboxUrl());
    }

    public function testSetGetThirdPartyProductsLiveUrl()
    {
        $this->struct->setThirdPartyProductsLiveUrl($url = 'http://some.sandox/url');
        $this->assertEquals($url, $this->struct->getThirdPartyProductsLiveUrl());
    }

    public function testSetGetCommandTimestamp()
    {
        $this->struct->setCommandTimestamp($timestamp = time());
        $this->assertEquals($timestamp, $this->struct->getCommandTimestamp());
    }

    public function testSetGetOauthToken()
    {
        $this->struct->setOauthToken($token = 'some-token');
        $this->assertEquals($token, $this->struct->getOauthToken());
    }

    public function testSetGetActivePayeverMethods()
    {
        $this->struct->setActivePayeverMethods($methods = ['some-method']);
        $this->assertEquals($methods, $this->struct->getActivePayeverMethods());
    }

    public function testSetIsProductsSyncEnabled()
    {
        $this->struct->setIsProductsSyncEnabled($flag = true);
        $this->assertEquals($flag, $this->struct->isProductsSyncEnabled());
    }

    public function testSetGetIsProductsAndInventoryOutwardSyncEnabled()
    {
        $this->struct->setIsProductsOutwardSyncEnabled($flag = true);
        $this->assertEquals($flag, $this->struct->isProductsOutwardSyncEnabled());
    }

    public function testSetGetProductsAndInventorySyncMode()
    {
        $this->struct->setProductsSyncMode($mode = PayeverSettingGeneralStruct::SYNC_MODE_CRON);
        $this->assertEquals($mode, $this->struct->getProductsSyncMode());
    }

    public function testSetGetProductsAndInventoryExternalId()
    {
        $this->struct->setProductsSyncExternalId($externalId = 'some-external-id');
        $this->assertEquals($externalId, $this->struct->getProductsSyncExternalId());
    }

    public function testValidate()
    {
        $this->struct->setClientId('some-client-id');
        $this->struct->setClientSecret('some-client-secret');
        $this->assertNull($this->struct->validate());
    }

    public function testValidateCaseInvalidClientId()
    {
        $this->expectException(PayeverSettingsInvalidException::class);
        $this->struct->validate();
    }

    public function testValidateCaseInvalidClientSecret()
    {
        $this->expectException(PayeverSettingsInvalidException::class);
        $this->struct->setClientId('some-client-id');
        $this->struct->validate();
    }
}
