<?php

namespace Payever\PayeverPayments\tests\unit\Service\PayeverApi\Plugins;

use Payever\ExternalIntegration\Plugins\Enum\PluginCommandNameEnum;
use Payever\ExternalIntegration\Plugins\Http\MessageEntity\PluginCommandEntity;
use Payever\PayeverPayments\Service\PayeverApi\Plugins\PluginCommandExecutor;
use Payever\PayeverPayments\Service\Setting\SettingsServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;

class PluginCommandExecutorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|SettingsServiceInterface
     */
    private $settingsService;

    /**
     * @var PluginCommandExecutor
     */
    private $executor;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->settingsService = $this->getMockBuilder(SettingsServiceInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->executor = new PluginCommandExecutor($this->settingsService);
    }

    public function testExecuteCommandCaseSandbox()
    {
        /** @var MockObject|PluginCommandEntity $command */
        $command = $this->getMockBuilder(PluginCommandEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getName', 'getValue'])
            ->getMock();
        $command->expects($this->once())
            ->method('getName')
            ->willReturn(PluginCommandNameEnum::SET_SANDBOX_HOST);
        $command->expects($this->once())
            ->method('getValue')
            ->willReturn('http://example.com');
        $this->settingsService->expects($this->once())
            ->method('updateSettings');
        $this->executor->executeCommand($command);
    }

    public function testExecuteCommandCaseLive()
    {
        /** @var MockObject|PluginCommandEntity $command */
        $command = $this->getMockBuilder(PluginCommandEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getName', 'getValue'])
            ->getMock();
        $command->expects($this->once())
            ->method('getName')
            ->willReturn(PluginCommandNameEnum::SET_LIVE_HOST);
        $command->expects($this->once())
            ->method('getValue')
            ->willReturn('http://example.com');
        $this->settingsService->expects($this->once())
            ->method('updateSettings');
        $this->executor->executeCommand($command);
    }

    public function testExecuteCommandCaseException()
    {
        /** @var MockObject|PluginCommandEntity $command */
        $command = $this->getMockBuilder(PluginCommandEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->expectException(\UnexpectedValueException::class);
        $this->executor->executeCommand($command);
    }
}
