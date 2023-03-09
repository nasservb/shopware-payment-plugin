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

use Payever\ExternalIntegration\Core\Enum\ChannelSet;
use Payever\ExternalIntegration\Plugins\Base\PluginRegistryInfoProviderInterface;
use Payever\ExternalIntegration\Plugins\Enum\PluginCommandNameEnum;
use Payever\PayeverPayments\PevrPayeverIntegration;
use Payever\PayeverPayments\Service\Setting\SettingsServiceInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginService;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\Routing\RouterInterface;

class PluginRegistryInfoProvider implements PluginRegistryInfoProviderInterface
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
     * @var string
     */
    private $shopwareKernelVersion;

    /**
     * @param PluginService $pluginService
     * @param RouterInterface $router
     * @param SettingsServiceInterface $settingsService
     * @param UriSigner $uriSigner
     * @param string $shopwareKernelVersion
     */
    public function __construct(
        PluginService $pluginService,
        RouterInterface $router,
        SettingsServiceInterface $settingsService,
        UriSigner $uriSigner,
        string $shopwareKernelVersion
    ) {
        $this->pluginService = $pluginService;
        $this->router = $router;
        $this->settingsService = $settingsService;
        $this->uriSigner = $uriSigner;
        $this->shopwareKernelVersion = $shopwareKernelVersion;
    }

    /**
     * @return string
     *
     * @throws \Shopware\Core\Framework\Plugin\Exception\PluginNotFoundException
     */
    public function getPluginVersion(): string
    {
        $plugin = $this->pluginService->getPluginByName(
            PevrPayeverIntegration::PLUGIN_NAME,
            Context::createDefaultContext()
        );

        return $plugin->getVersion();
    }

    /**
     * @inheritDoc
     */
    public function getCmsVersion(): string
    {
        return $this->shopwareKernelVersion;
    }

    /**
     * @inheritDoc
     */
    public function getHost(): string
    {
        return $this->router->generate('frontend.home.page', [], RouterInterface::ABSOLUTE_URL);
    }

    /**
     * @inheritDoc
     */
    public function getChannel(): string
    {
        return ChannelSet::CHANNEL_SHOPWARE;
    }

    /**
     * @inheritDoc
     */
    public function getSupportedCommands(): array
    {
        return [
            PluginCommandNameEnum::SET_LIVE_HOST,
            PluginCommandNameEnum::SET_SANDBOX_HOST,
            PluginCommandNameEnum::NOTIFY_NEW_PLUGIN_VERSION,
            PluginCommandNameEnum::SET_API_VERSION,
        ];
    }

    /**
     * @inheritDoc
     */
    public function getCommandEndpoint(): string
    {
        return $this->uriSigner->sign(
            $this->router->generate('payever.plugin.execute_commands', [], RouterInterface::ABSOLUTE_URL)
        );
    }

    /**
     * @inheritDoc
     */
    public function getBusinessIds(): array
    {
        try {
            $businessUuid = $this->settingsService->getSettings()->getBusinessUuid();
        } catch (\Exception $exception) {
            // settings are not filled in yet
            $businessUuid = '';
        }

        return [$businessUuid];
    }
}
