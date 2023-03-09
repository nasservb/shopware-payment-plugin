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

namespace Payever\PayeverPayments\Service\PayeverApi\Plugins;

use Payever\ExternalIntegration\Plugins\Command\AbstractPluginCommandExecutor;
use Payever\ExternalIntegration\Plugins\Enum\PluginCommandNameEnum;
use Payever\ExternalIntegration\Plugins\Http\MessageEntity\PluginCommandEntity;
use Payever\PayeverPayments\Service\Setting\SettingsServiceInterface;

class PluginCommandExecutor extends AbstractPluginCommandExecutor
{
    /**
     * @var SettingsServiceInterface
     */
    private $settingsService;

    /**
     * @param SettingsServiceInterface $settingsService
     */
    public function __construct(SettingsServiceInterface $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * @param PluginCommandEntity $command
     *
     * @return bool|void
     *
     * @throws \UnexpectedValueException
     */
    public function executeCommand(PluginCommandEntity $command)
    {
        $name = $command->getName();
        $value = $command->getValue();
        switch ($name) {
            case PluginCommandNameEnum::SET_SANDBOX_HOST:
                $this->assertApiHostValid($value);
                $this->settingsService->updateSettings(['sandboxUrl' => $value]);
                break;
            case PluginCommandNameEnum::SET_LIVE_HOST:
                $this->assertApiHostValid($value);
                $this->settingsService->updateSettings(['liveUrl' => $value]);
                break;
            case PluginCommandNameEnum::SET_API_VERSION:
                $this->settingsService->updateSettings(['apiVersion' => $value]);
                break;
            default:
                throw new \UnexpectedValueException(
                    sprintf(
                        'Command %s with value %s is not supported',
                        $command->getId(),
                        $value
                    )
                );
        }
    }
}
