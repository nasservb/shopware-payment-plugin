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
use Shopware\Core\Framework\Struct\Struct;

/**
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class PayeverSettingGeneralStruct extends Struct
{
    public const SYNC_MODE_INSTANT = 'instant';
    public const SYNC_MODE_CRON = 'cron';

    /**
     * @var bool
     */
    protected $isSandbox = false;

    /**
     * @var string
     */
    protected $clientId;

    /**
     * @var string
     */
    protected $clientSecret;

    /**
     * @var string
     */
    protected $businessUuid;

    /**
     * @var bool
     */
    protected $isIframe = false;

    /**
     * @var string
     */
    protected $checkoutLanguage;

    /**
     * @var bool
     */
    protected $isForceRedirect = true;

    /**
     * @var int
     */
    protected $apiVersion = 2;

    /**
     * @var bool
     */
    protected $saveOrderOnError = false;

    /**
     * @var string
     */
    protected $sandboxUrl;

    /**
     * @var string
     */
    protected $liveUrl;

    /**
     * @var string
     */
    protected $thirdPartyProductsSandboxUrl;

    /**
     * @var string
     */
    protected $thirdPartyProductsLiveUrl;

    /**
     * @var int
     */
    protected $commandTimestamp;

    /**
     * @var string
     */
    protected $oauthToken;

    /**
     * @var array
     */
    protected $activePayeverMethods = [];

    /**
     * @var bool
     */
    protected $isProductsSyncEnabled = false;

    /**
     * @var bool
     */
    protected $isProductsOutwardSyncEnabled = true;

    /**
     * @var string
     */
    protected $productsSyncMode = self::SYNC_MODE_INSTANT;

    /**
     * @var string|null
     */
    protected $productsSyncExternalId;

    /**
     * @return bool
     */
    public function isSandbox(): bool
    {
        return $this->isSandbox;
    }

    /**
     * @param bool $isSandbox
     */
    public function setSandbox(bool $isSandbox): void
    {
        $this->isSandbox = $isSandbox;
    }

    /**
     * @return string|null
     */
    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    /**
     * @param string $clientId
     */
    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    /**
     * @return string|null
     */
    public function getClientSecret(): ?string
    {
        return $this->clientSecret;
    }

    /**
     * @param string $clientSecret
     */
    public function setClientSecret(string $clientSecret): void
    {
        $this->clientSecret = $clientSecret;
    }

    /**
     * @return string|null
     */
    public function getBusinessUuid(): ?string
    {
        return $this->businessUuid;
    }

    /**
     * @param string $businessUuid
     */
    public function setBusinessUuid(string $businessUuid): void
    {
        $this->businessUuid = $businessUuid;
    }

    /**
     * @return bool
     */
    public function isIframe(): bool
    {
        return $this->isIframe;
    }

    /**
     * @param bool $isIframe
     */
    public function setIsIframe(bool $isIframe): void
    {
        $this->isIframe = $isIframe;
    }

    /**
     * @return string|null
     */
    public function getCheckoutLanguage(): ?string
    {
        return $this->checkoutLanguage;
    }

    /**
     * @param string $checkoutLanguage
     */
    public function setCheckoutLanguage(string $checkoutLanguage): void
    {
        $this->checkoutLanguage = $checkoutLanguage;
    }

    /**
     * @return bool
     */
    public function isForceRedirect(): bool
    {
        return $this->isForceRedirect;
    }

    /**
     * @param bool $isForceRedirect
     * @return $this
     */
    public function setIsForceRedirect(bool $isForceRedirect): self
    {
        $this->isForceRedirect = $isForceRedirect;

        return $this;
    }

    /**
     * @return bool
     */
    public function saveOrderOnError(): bool
    {
        return $this->saveOrderOnError;
    }

    /**
     * @param bool $saveOrderOnError
     */
    public function setSaveOrderOnError(bool $saveOrderOnError): void
    {
        $this->saveOrderOnError = $saveOrderOnError;
    }

    /**
     * @return string|null
     */
    public function getSandboxUrl(): ?string
    {
        return $this->sandboxUrl;
    }

    /**
     * @param string $sandboxUrl
     */
    public function setSandboxUrl(string $sandboxUrl): void
    {
        $this->sandboxUrl = $sandboxUrl;
    }

    /**
     * @return string|null
     */
    public function getLiveUrl(): ?string
    {
        return $this->liveUrl;
    }

    /**
     * @param string $liveUrl
     */
    public function setLiveUrl(string $liveUrl): void
    {
        $this->liveUrl = $liveUrl;
    }

    /**
     * @return string|null
     */
    public function getThirdPartyProductsSandboxUrl(): ?string
    {
        return $this->thirdPartyProductsSandboxUrl;
    }

    /**
     * @param string $thirdPartyProductsSandboxUrl
     */
    public function setThirdPartyProductsSandboxUrl(string $thirdPartyProductsSandboxUrl): void
    {
        $this->thirdPartyProductsSandboxUrl = $thirdPartyProductsSandboxUrl;
    }

    /**
     * @return string|null
     */
    public function getThirdPartyProductsLiveUrl(): ?string
    {
        return $this->thirdPartyProductsLiveUrl;
    }

    /**
     * @param string $thirdPartyProductsLiveUrl
     */
    public function setThirdPartyProductsLiveUrl(string $thirdPartyProductsLiveUrl): void
    {
        $this->thirdPartyProductsLiveUrl = $thirdPartyProductsLiveUrl;
    }

    /**
     * @return int|null
     */
    public function getCommandTimestamp(): ?int
    {
        return $this->commandTimestamp;
    }

    /**
     * @param int $commandTimestamp
     */
    public function setCommandTimestamp(int $commandTimestamp): void
    {
        $this->commandTimestamp = $commandTimestamp;
    }

    /**
     * @return string|null
     */
    public function getOauthToken(): ?string
    {
        return $this->oauthToken;
    }

    /**
     * @param string $oauthToken
     */
    public function setOauthToken(string $oauthToken): void
    {
        $this->oauthToken = $oauthToken;
    }

    /**
     * @return array
     */
    public function getActivePayeverMethods(): array
    {
        return $this->activePayeverMethods;
    }

    /**
     * @param array $activePayeverMethods
     */
    public function setActivePayeverMethods(array $activePayeverMethods): void
    {
        $this->activePayeverMethods = $activePayeverMethods;
    }

    /**
     * @return bool
     */
    public function isProductsSyncEnabled(): bool
    {
        return (bool) $this->isProductsSyncEnabled;
    }

    /**
     * @param bool $isProductsSyncEnabled
     */
    public function setIsProductsSyncEnabled(bool $isProductsSyncEnabled): void
    {
        $this->isProductsSyncEnabled = $isProductsSyncEnabled;
    }

    /**
     * @return bool
     */
    public function isProductsOutwardSyncEnabled(): bool
    {
        return (bool) $this->isProductsOutwardSyncEnabled;
    }

    /**
     * @param bool $isProductsOutwardSyncEnabled
     */
    public function setIsProductsOutwardSyncEnabled(bool $isProductsOutwardSyncEnabled): void
    {
        $this->isProductsOutwardSyncEnabled = $isProductsOutwardSyncEnabled;
    }

    /**
     * @return string
     */
    public function getProductsSyncMode(): string
    {
        return $this->productsSyncMode;
    }

    /**
     * @param string $productsSyncMode
     */
    public function setProductsSyncMode(string $productsSyncMode): void
    {
        $this->productsSyncMode = $productsSyncMode;
    }

    /**
     * @return string|null
     */
    public function getProductsSyncExternalId(): ?string
    {
        return $this->productsSyncExternalId;
    }

    /**
     * @param string|null $productsSyncExternalId
     */
    public function setProductsSyncExternalId(string $productsSyncExternalId = null): void
    {
        $this->productsSyncExternalId = $productsSyncExternalId;
    }

    /**
     * @throws PayeverSettingsInvalidException
     */
    public function validate(): void
    {
        if ($this->clientId === null) {
            throw new PayeverSettingsInvalidException('clientId');
        }

        if ($this->clientSecret === null) {
            throw new PayeverSettingsInvalidException('clientSecret');
        }
    }
}
