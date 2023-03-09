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

namespace Payever\PayeverPayments\Service\Setting;

use Payever\PayeverPayments\Service\Setting\Exception\PayeverSettingsInvalidException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SettingsService implements SettingsServiceInterface
{
    private const SYSTEM_CONFIG_DOMAIN = 'PevrPayeverIntegration.config.';

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var PayeverSettingGeneralStruct[]
     */
    private $configCache = [];

    /**
     * @param SystemConfigService $systemConfigService
     */
    public function __construct(SystemConfigService $systemConfigService)
    {
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @param string|null $salesChannelId
     *
     * @return PayeverSettingGeneralStruct
     *
     * @throws PayeverSettingsInvalidException
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     * @throws \Shopware\Core\Framework\Uuid\Exception\InvalidUuidException
     * @throws \Shopware\Core\System\SystemConfig\Exception\InvalidDomainException
     */
    public function getSettings(?string $salesChannelId = null): PayeverSettingGeneralStruct
    {
        if (isset($this->configCache[$salesChannelId])) {
            return $this->configCache[$salesChannelId];
        }

        $values = $this->systemConfigService->getDomain(
            self::SYSTEM_CONFIG_DOMAIN,
            $salesChannelId,
            true
        );

        $propertyValuePairs = [];

        /** @var string $key */
        foreach ($values as $key => $value) {
            $property = substr($key, strlen(self::SYSTEM_CONFIG_DOMAIN));
            $propertyValuePairs[$property] = $value;
        }

        $settingsEntity = new PayeverSettingGeneralStruct();
        $settingsEntity->assign($propertyValuePairs);
        $settingsEntity->validate();

        $this->configCache[$salesChannelId] = $settingsEntity;

        return $settingsEntity;
    }

    /**
     * @inheritDoc
     */
    public function updateSettings(array $settings, ?string $salesChannelId = null): void
    {
        foreach ($settings as $key => $value) {
            $this->systemConfigService->set(
                self::SYSTEM_CONFIG_DOMAIN . $key,
                $value,
                $salesChannelId
            );
        }

        $this->configCache = [];
    }

    /**
     * Resets cache
     */
    public function resetCache()
    {
        $this->configCache = [];
    }
}
