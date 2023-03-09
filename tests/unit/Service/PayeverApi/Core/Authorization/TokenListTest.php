<?php

namespace Payever\PayeverPayments\tests\unit\Service\PayeverApi\Core\Authorization;

use Payever\PayeverPayments\Service\PayeverApi\Core\Authorization\TokenList;
use Payever\PayeverPayments\Service\Setting\PayeverSettingGeneralStruct;
use Payever\PayeverPayments\Service\Setting\SettingsServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;

class TokenListTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|SettingsServiceInterface
     */
    protected $settingsService;

    /**
     * @var TokenList
     */
    protected $tokenList;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->settingsService = $this->getMockBuilder(SettingsServiceInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->tokenList = new TokenList($this->settingsService);
    }

    public function testLoad()
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $settings = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $settings->expects($this->once())
            ->method('getOauthToken')
            ->willReturn(\json_encode(['some_token_name' => ['scope' => 'api']]));
        $this->tokenList->load();
    }

    public function testSave()
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $settings = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $settings->expects($this->once())
            ->method('getOauthToken')
            ->willReturn(\json_encode(['some_token_name' => ['scope' => 'api']]));
        $this->tokenList->load();
        $this->settingsService->expects($this->once())
            ->method('updateSettings');
        $this->tokenList->save();
    }
}
