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

namespace Payever\PayeverPayments\Twig;

use Payever\PayeverPayments\Service\Setting\SettingsService;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class TemplateDataExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @param RequestStack $requestStack
     * @param SettingsService $settingsService
     */
    public function __construct(RequestStack $requestStack, SettingsService $settingsService)
    {
        $this->requestStack = $requestStack;
        $this->settingsService = $settingsService;
    }

    /**
     * @inheritDoc
     */
    public function getGlobals(): array
    {
        $businessUuid = '';
        try {
            $businessUuid = $this->settingsService->getSettings()->getBusinessUuid();
            $request = $this->requestStack->getCurrentRequest();
            if ($request) {
                /** @var SalesChannelContext|null $context */
                $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
                if ($context) {
                    $businessUuid = $this->settingsService->getSettings($context->getSalesChannel()->getId())
                        ->getBusinessUuid();
                }
            }
        } catch (\Exception $e) {
            // do not log anything as it is not specific case when credentials are not set
        }

        return [
            'payever' => [
                'config' => [
                    'businessUuid' => $businessUuid,
                ],
            ],
        ];
    }
}
