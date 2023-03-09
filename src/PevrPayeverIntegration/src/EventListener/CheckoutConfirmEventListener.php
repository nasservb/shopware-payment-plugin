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

namespace Payever\PayeverPayments\EventListener;

use Payever\PayeverPayments\PevrPayeverIntegration;
use Payever\PayeverPayments\Service\Payment\HiddenMethodService;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CheckoutConfirmEventListener implements EventSubscriberInterface
{
    /** @var HiddenMethodService */
    private $hiddenMethodService;

    /**
     * CheckoutConfirmEventListener constructor.
     * @param HiddenMethodService $hiddenMethodService
     */
    public function __construct(HiddenMethodService $hiddenMethodService)
    {
        $this->hiddenMethodService = $hiddenMethodService;
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onCheckoutConfirm',
            AccountEditOrderPageLoadedEvent::class => 'onAccountEditOrder',
        ];
    }

    /**
     * @param AccountEditOrderPageLoadedEvent $event
     *
     * @throws \Payever\PayeverPayments\Service\Setting\Exception\PayeverSettingsInvalidException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function onAccountEditOrder(AccountEditOrderPageLoadedEvent $event): void
    {
        $hiddenMethodsRow = $this->hiddenMethodService->getCurrentHiddenMethods($event->getPage()->getOrder()->getId());
        $hiddenMethods = $hiddenMethodsRow ? json_decode($hiddenMethodsRow->getHiddenMethods(), true) : [];
        $hiddenMethodsFromSession = $this->hiddenMethodService->getCurrentHiddenMethodsFromSession();
        foreach (array_keys($hiddenMethodsFromSession) as $methodCode) {
            $hiddenMethods[$methodCode] = time();
        }

        $this->hiddenMethodService->setHiddenMethodsToSession($hiddenMethods);

        $paymentMethodCollection = $event->getPage()->getPaymentMethods();
        $salesChannelContext = $event->getSalesChannelContext();

        $this->processPaymentMethods($paymentMethodCollection, $salesChannelContext, $hiddenMethods);
    }

    /**
     * @param CheckoutConfirmPageLoadedEvent $event
     *
     * @throws \Payever\PayeverPayments\Service\Setting\Exception\PayeverSettingsInvalidException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function onCheckoutConfirm(CheckoutConfirmPageLoadedEvent $event): void
    {
        $paymentMethodCollection = $event->getPage()->getPaymentMethods();
        $salesChannelContext = $event->getSalesChannelContext();
        $hiddenMethods = $this->hiddenMethodService->getCurrentHiddenMethodsFromSession();

        $this->processPaymentMethods($paymentMethodCollection, $salesChannelContext, $hiddenMethods);
    }

    /**
     * @param PaymentMethodCollection $paymentMethodCollection
     * @param SalesChannelContext $salesChannelContext
     * @param array $hiddenList
     */
    private function processPaymentMethods(
        PaymentMethodCollection $paymentMethodCollection,
        SalesChannelContext $salesChannelContext,
        array $hiddenList
    ): void {
        foreach ($paymentMethodCollection->getElements() as $paymentMethod) {
            if ($this->hiddenMethodService->isMethodHidden($paymentMethod, $hiddenList)) {
                $paymentMethodCollection->remove($paymentMethod->getId());
            }
        }

        foreach ($paymentMethodCollection->getElements() as $paymentMethod) {
            $customFields = $paymentMethod->getCustomFields() ?? [];
            if (
                isset($customFields[PevrPayeverIntegration::CUSTOM_FIELD_ACCEPT_FEE])
                && !$customFields[PevrPayeverIntegration::CUSTOM_FIELD_ACCEPT_FEE]
            ) {
                $currency = $salesChannelContext->getCurrency()->getSymbol();
                $customName = $this->getCustomName($customFields, $currency);

                $paymentMethod->setName($paymentMethod->getName() . $customName);
                $translated = $paymentMethod->getTranslated();
                $translated['name'] = $translated['name'] . $customName;
                $paymentMethod->setTranslated($translated);
            }
        }
    }

    /**
     * @param array $customFields
     * @param string $currency
     * @return string
     */
    private function getCustomName(array $customFields, string $currency): string
    {
        $customName = '';
        $fixedFee = $customFields[PevrPayeverIntegration::CUSTOM_FIELD_FIXED_FEE];
        $variableFee = $customFields[PevrPayeverIntegration::CUSTOM_FIELD_VARIABLE_FEE];
        if ($fixedFee > 0 && $variableFee > 0) {
            $customName = sprintf(
                ' (%s%s + %s%%)',
                $fixedFee,
                $currency,
                $variableFee
            );
        } elseif ($fixedFee == 0 && $variableFee > 0) {
            $customName = sprintf(
                ' (+ %s%%)',
                $variableFee
            );
        } elseif (0 < $fixedFee && 0 == $variableFee) {
            $customName = sprintf(
                ' (+ %s%s)',
                $fixedFee,
                $currency
            );
        }

        return $customName;
    }
}
