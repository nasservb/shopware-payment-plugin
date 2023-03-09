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

namespace Payever\PayeverPayments\Service\Helper;

use Payever\PayeverPayments\Service\Setting\PayeverSettingGeneralStruct;
use Payever\PayeverPayments\Service\Setting\SettingsService;
use Psr\Log\LoggerInterface;

class ConfigHelper
{
    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param SettingsService $settingsService
     * @param LoggerInterface $logger
     */
    public function __construct(SettingsService $settingsService, LoggerInterface $logger)
    {
        $this->settingsService = $settingsService;
        $this->logger = $logger;
    }

    /**
     * @return bool
     */
    public function isProductsSyncEnabled(): bool
    {
        return (bool) $this->callMethod('isProductsSyncEnabled');
    }

    /**
     * @return bool
     */
    public function isProductsOutwardSyncEnabled(): bool
    {
        return (bool) $this->callMethod('isProductsOutwardSyncEnabled');
    }

    /**
     * @return string|null
     */
    public function getProductsSyncExternalId(): ?string
    {
        return $this->callMethod('getProductsSyncExternalId');
    }

    /**
     * @return string|null
     */
    public function getBusinessUuid(): ?string
    {
        return $this->callMethod('getBusinessUuid');
    }

    /**
     * @return bool
     */
    public function isCronMode(): bool
    {
        return $this->callMethod('getProductsSyncMode') === PayeverSettingGeneralStruct::SYNC_MODE_CRON;
    }

    /**
     * @param string $method
     * @return mixed
     */
    private function callMethod(string $method)
    {
        $result = null;
        try {
            $result = call_user_func([$this->settingsService->getSettings(), $method]);
        } catch (\Throwable $t) {
            $this->logger->warning($t->getMessage());
        }

        return $result;
    }
}
