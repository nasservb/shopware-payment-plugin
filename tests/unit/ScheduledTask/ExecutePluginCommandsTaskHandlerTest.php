<?php

namespace Payever\PayeverPayments\tests\unit\ScheduledTask;

use Payever\ExternalIntegration\Plugins\Command\PluginCommandManager;
use Payever\ExternalIntegration\Plugins\PluginsApiClient;
use Payever\PayeverPayments\ScheduledTask\ExecutePluginCommandsTaskHandler;
use Payever\PayeverPayments\Service\Setting\PayeverSettingGeneralStruct;
use Payever\PayeverPayments\Service\Setting\SettingsService;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class ExecutePluginCommandsTaskHandlerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $scheduledTaskRepository;

    /**
     * @var MockObject|PluginsApiClient
     */
    private $pluginsApiClient;

    /**
     * @var MockObject|SettingsService
     */
    private $settingsService;

    /**
     * @var MockObject|PluginCommandManager
     */
    private $pluginCommandManager;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var ExecutePluginCommandsTaskHandler
     */
    private $handler;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->scheduledTaskRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->pluginsApiClient = $this->getMockBuilder(PluginsApiClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->settingsService = $this->getMockBuilder(SettingsService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->pluginCommandManager = $this->getMockBuilder(PluginCommandManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->handler = new ExecutePluginCommandsTaskHandler(
            $this->scheduledTaskRepository,
            $this->pluginsApiClient,
            $this->settingsService,
            $this->pluginCommandManager,
            $this->logger
        );
    }

    public function testGetHandledMessages()
    {
        $this->assertNotEmpty($this->handler->getHandledMessages());
    }

    public function testRun()
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $settings = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $settings->expects($this->once())
            ->method('getCommandTimestamp')
            ->willReturn(0);
        $this->pluginCommandManager->expects($this->once())
            ->method('executePluginCommands');
        $this->handler->run();
    }

    public function testRunCaseException()
    {
        $this->settingsService->expects($this->once())
            ->method('getSettings')
            ->willReturn(
                $settings = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $settings->expects($this->once())
            ->method('getCommandTimestamp')
            ->willReturn(0);
        $this->pluginCommandManager->expects($this->once())
            ->method('executePluginCommands')
            ->willThrowException(new \Exception());
        $this->handler->run();
    }
}
