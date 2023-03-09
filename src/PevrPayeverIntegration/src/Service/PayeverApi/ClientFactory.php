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

namespace Payever\PayeverPayments\Service\PayeverApi;

use Payever\ExternalIntegration\Core\ClientConfiguration;
use Payever\ExternalIntegration\Core\Enum\ChannelSet;
use Payever\ExternalIntegration\Inventory\InventoryApiClient;
use Payever\ExternalIntegration\Payments\PaymentsApiClient;
use Payever\ExternalIntegration\Payments\ThirdPartyPluginsApiClient;
use Payever\ExternalIntegration\Plugins\PluginsApiClient;
use Payever\ExternalIntegration\Products\ProductsApiClient;
use Payever\ExternalIntegration\ThirdParty\ThirdPartyApiClient;
use Payever\PayeverPayments\Service\PayeverApi\Core\Authorization\TokenList;
use Payever\PayeverPayments\Service\PayeverApi\Plugins\PluginRegistryInfoProvider;
use Payever\PayeverPayments\Service\Setting\PayeverSettingGeneralStruct;
use Payever\PayeverPayments\Service\Setting\SettingsServiceInterface;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class ClientFactory
{
    /**
     * @var SettingsServiceInterface
     */
    private $settingsService;

    /**
     * @var PluginRegistryInfoProvider
     */
    private $registryInfoProvider;

    /**
     * @var TokenList
     */
    private $tokenList;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PaymentsApiClient[]
     */
    private $paymentsApiClients = [];

    /**
     * @var ThirdPartyPluginsApiClient[]
     */
    private $thirdPartyPluginsApiClient = [];

    /**
     * @var PluginsApiClient[]
     */
    private $pluginsApiClients = [];

    /**
     * @var ThirdPartyApiClient
     */
    private $thirdPartyApiClient;

    /**
     * @var ProductsApiClient
     */
    private $productApiClient;

    /**
     * @var InventoryApiClient
     */
    private $inventoryApiClient;

    /**
     * @var ClientConfiguration[]
     */
    private $clientConfigurations = [];

    /**
     * @var ClientConfiguration[]
     */
    private $thirdPartyProductsClientCfgs = [];

    /**
     * @param SettingsServiceInterface $settingsService
     * @param PluginRegistryInfoProvider $pluginRegistryInfoProvider
     * @param TokenList $tokenList
     * @param LoggerInterface $logger
     */
    public function __construct(
        SettingsServiceInterface $settingsService,
        PluginRegistryInfoProvider $pluginRegistryInfoProvider,
        TokenList $tokenList,
        LoggerInterface $logger
    ) {
        $this->settingsService = $settingsService;
        $this->registryInfoProvider = $pluginRegistryInfoProvider;
        $this->tokenList = $tokenList;
        $this->logger = $logger;
    }

    /**
     * @param string|null $salesChannelId
     *
     * @return PaymentsApiClient
     *
     * @throws \Exception
     */
    public function getPaymentsApiClient(?string $salesChannelId = null): PaymentsApiClient
    {
        if (!isset($this->paymentsApiClients[$salesChannelId])) {
            $this->paymentsApiClients[$salesChannelId] = new PaymentsApiClient(
                $this->getClientConfiguration($salesChannelId),
                $this->tokenList
            );
        }

        return $this->paymentsApiClients[$salesChannelId];
    }

    /**
     * @param string|null $salesChannelId
     *
     * @return ThirdPartyPluginsApiClient
     *
     * @throws \Exception
     */
    public function getThirdPartyPluginsApiClient(?string $salesChannelId = null): ThirdPartyPluginsApiClient
    {
        if (!isset($this->thirdPartyPluginsApiClient[$salesChannelId])) {
            $this->thirdPartyPluginsApiClient[$salesChannelId] = new ThirdPartyPluginsApiClient(
                $this->getClientConfiguration($salesChannelId),
                $this->tokenList
            );
        }

        return $this->thirdPartyPluginsApiClient[$salesChannelId];
    }

    /**
     * @param string|null $salesChannelId
     *
     * @return PluginsApiClient
     *
     * @throws \Exception
     */
    public function getPluginsApiClient(?string $salesChannelId = null): PluginsApiClient
    {
        if (!isset($this->pluginsApiClients[$salesChannelId])) {
            $this->pluginsApiClients[$salesChannelId] = new PluginsApiClient(
                $this->registryInfoProvider,
                $this->getClientConfiguration($salesChannelId),
                $this->tokenList
            );
        }

        return $this->pluginsApiClients[$salesChannelId];
    }

    /**
     * @return ThirdPartyApiClient
     * @throws \Exception
     */
    public function getThirdPartyApiClient(): ThirdPartyApiClient
    {
        if (null === $this->thirdPartyApiClient) {
            $this->thirdPartyApiClient = new ThirdPartyApiClient($this->getThirdPartyProductsClientConfigs());
        }
        return $this->thirdPartyApiClient;
    }

    /**
     * @return ProductsApiClient
     * @throws \Exception
     */
    public function getProductsApiClient(): ProductsApiClient
    {
        if (null === $this->productApiClient) {
            $this->productApiClient = new ProductsApiClient($this->getThirdPartyProductsClientConfigs());
        }
        return $this->productApiClient;
    }

    /**
     * @return InventoryApiClient
     * @throws \Exception
     */
    public function getInventoryApiClient(): InventoryApiClient
    {
        if (null === $this->inventoryApiClient) {
            $this->inventoryApiClient = new InventoryApiClient($this->getThirdPartyProductsClientConfigs());
        }
        return $this->inventoryApiClient;
    }

    /**
     * @retrun void
     */
    public function reset(): void
    {
        $this->paymentsApiClients = $this->paymentsApiClients = [];
        $this->clientConfigurations = $this->thirdPartyProductsClientCfgs = [];
        $this->thirdPartyApiClient = $this->productApiClient = $this->inventoryApiClient = null;
    }

    /**
     * @param string|null $salesChannelId
     * @return ClientConfiguration
     * @throws \Payever\PayeverPayments\Service\Setting\Exception\PayeverSettingsInvalidException
     * @throws \Exception
     */
    private function getClientConfiguration(?string $salesChannelId = null): ClientConfiguration
    {
        if (!isset($this->clientConfigurations[$salesChannelId])) {
            $this->clientConfigurations[$salesChannelId] = $this->loadClientConfiguration(
                $this->settingsService->getSettings($salesChannelId)
            );
        }

        return $this->clientConfigurations[$salesChannelId];
    }

    /**
     * @param string|null $salesChannelId
     * @return ClientConfiguration
     * @throws \Exception
     */
    private function getThirdPartyProductsClientConfigs(?string $salesChannelId = null): ClientConfiguration
    {
        if (!isset($this->thirdPartyProductsClientCfgs[$salesChannelId])) {
            $settings = $this->settingsService->getSettings($salesChannelId);
            $clientConfig = $this->loadClientConfiguration($settings);
            $clientConfig->setCustomSandboxUrl(null);
            $sandboxUrl = $settings->getThirdPartyProductsSandboxUrl();
            if ($sandboxUrl) {
                $clientConfig->setCustomSandboxUrl($sandboxUrl);
            }
            $clientConfig->setCustomLiveUrl(null);
            $liveUrl = $settings->getThirdPartyProductsLiveUrl();
            if ($liveUrl) {
                $clientConfig->setCustomLiveUrl($liveUrl);
            }
            $this->thirdPartyProductsClientCfgs[$salesChannelId]  = $clientConfig;
        }

        return $this->thirdPartyProductsClientCfgs[$salesChannelId];
    }

    /**
     * @param PayeverSettingGeneralStruct $settings
     * @return ClientConfiguration
     * @throws \Exception
     */
    private function loadClientConfiguration(PayeverSettingGeneralStruct $settings): ClientConfiguration
    {
        $clientConfiguration = new ClientConfiguration();
        $apiMode = $settings->isSandbox()
            ? ClientConfiguration::API_MODE_SANDBOX
            : ClientConfiguration::API_MODE_LIVE;

        $clientConfiguration->setChannelSet(ChannelSet::CHANNEL_SHOPWARE)
            ->setApiMode($apiMode)
            ->setClientId($settings->getClientId())
            ->setClientSecret($settings->getClientSecret())
            ->setBusinessUuid($settings->getBusinessUuid())
            ->setCustomSandboxUrl($settings->getSandboxUrl())
            ->setCustomLiveUrl($settings->getLiveUrl())
            ->setLogger($this->logger);

        return $clientConfiguration;
    }
}
