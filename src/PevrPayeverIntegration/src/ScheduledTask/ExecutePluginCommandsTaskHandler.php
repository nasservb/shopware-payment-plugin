<?php

/**
 * payever GmbH
 *
 * NOTICE OF LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade payever Shopware package
 * to newer versions in the future.
 *
 * @category    Payever
 * @author      payever GmbH <service@payever.de>
 * @copyright   Copyright (c) 2021 payever GmbH (http://www.payever.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Payever\PayeverPayments\ScheduledTask;

use Payever\ExternalIntegration\Plugins\Command\PluginCommandManager;
use Payever\ExternalIntegration\Plugins\PluginsApiClient;
use Payever\PayeverPayments\Service\Setting\SettingsService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class ExecutePluginCommandsTaskHandler extends \Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler
{
    /**
     * @var PluginsApiClient
     */
    private $pluginsApiClient;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var PluginCommandManager
     */
    private $pluginCommandManager;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param EntityRepositoryInterface $scheduledTaskRepository
     * @param PluginsApiClient $pluginsApiClient
     * @param SettingsService $settingsService
     * @param PluginCommandManager $pluginCommandManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        PluginsApiClient $pluginsApiClient,
        SettingsService $settingsService,
        PluginCommandManager $pluginCommandManager,
        LoggerInterface $logger
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->pluginsApiClient = $pluginsApiClient;
        $this->settingsService = $settingsService;
        $this->pluginCommandManager = $pluginCommandManager;
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public static function getHandledMessages(): iterable
    {
        return [ExecutePluginCommandsTask::class];
    }

    /**
     * {@inheritDoc}
     */
    public function run(): void
    {
        try {
            $timestamp = $this->settingsService->getSettings()->getCommandTimestamp();

            $this->pluginsApiClient->registerPlugin();
            $this->pluginCommandManager->executePluginCommands($timestamp);

            $this->settingsService->updateSettings(['commandTimestamp' => time()]);
        } catch (\Exception $exception) {
            $this->logger->warning(sprintf('Plugin command execution failed: %s', $exception->getMessage()));
        }
    }
}
