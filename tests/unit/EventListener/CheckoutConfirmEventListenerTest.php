<?php

namespace Payever\PayeverPayments\tests\unit\EventListener;

use Payever\PayeverPayments\EventListener\CheckoutConfirmEventListener;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPage;
use Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Payever\PayeverPayments\HiddenMethods\HiddenMethodsEntity;
use Payever\PayeverPayments\PevrPayeverIntegration;
use Payever\PayeverPayments\Service\Payment\HiddenMethodService;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;

class CheckoutConfirmEventListenerTest extends \PHPUnit\Framework\TestCase
{
    /** @var MockObject|HiddenMethodService */
    private $hiddenMethodService;

    /** @var CheckoutConfirmEventListener */
    private $listener;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->hiddenMethodService = $this->getMockBuilder(HiddenMethodService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->listener = new CheckoutConfirmEventListener($this->hiddenMethodService);
    }

    public function testGetSubscribedEvents()
    {
        $this->assertNotEmpty($this->listener->getSubscribedEvents());
    }

    public function testOnCheckoutConfirm()
    {
        /** @var MockObject|CheckoutConfirmPageLoadedEvent $event */
        $event = $this->getMockBuilder(CheckoutConfirmPageLoadedEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getPage')
            ->willReturn(
                $page = $this->getMockBuilder(CheckoutConfirmPage::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $page->expects($this->once())
            ->method('getPaymentMethods')
            ->willReturn(
                $paymentMethodCollection = $this->getMockBuilder(PaymentMethodCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $event->expects($this->once())
            ->method('getSalesChannelContext')
            ->willReturn(
                $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->hiddenMethodService->expects($this->once())
            ->method('getCurrentHiddenMethodsFromSession')
            ->willReturn(
                $hiddenMethods = ['paypal' => 'paypal']
            );
        $paymentMethodCollection->expects($this->any())
            ->method('getElements')
            ->willReturn([
                $paymentMethod = $this->getMockBuilder(PaymentMethodEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $this->hiddenMethodService->expects($this->once())
            ->method('isMethodHidden')
            ->willReturn(true);
        $paymentMethod->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([
                PevrPayeverIntegration::CUSTOM_FIELD_ACCEPT_FEE => false,
                PevrPayeverIntegration::CUSTOM_FIELD_FIXED_FEE => 1,
                PevrPayeverIntegration::CUSTOM_FIELD_VARIABLE_FEE => 1,
            ]);
        $salesChannelContext->expects($this->once())
            ->method('getCurrency')
            ->willReturn(
                $currencyEntity = $this->getMockBuilder(CurrencyEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $currencyEntity->expects($this->once())
            ->method('getSymbol')
            ->willReturn('EUR');
        $paymentMethod->expects($this->once())
            ->method('getTranslated')
            ->willReturn(['name' => 'some-name']);
        $this->listener->onCheckoutConfirm($event);
    }

    public function testOnCheckoutConfirmCaseVariableFee()
    {
        /** @var MockObject|CheckoutConfirmPageLoadedEvent $event */
        $event = $this->getMockBuilder(CheckoutConfirmPageLoadedEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getPage')
            ->willReturn(
                $page = $this->getMockBuilder(CheckoutConfirmPage::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $page->expects($this->once())
            ->method('getPaymentMethods')
            ->willReturn(
                $paymentMethodCollection = $this->getMockBuilder(PaymentMethodCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $event->expects($this->once())
            ->method('getSalesChannelContext')
            ->willReturn(
                $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->hiddenMethodService->expects($this->once())
            ->method('getCurrentHiddenMethodsFromSession')
            ->willReturn(
                $hiddenMethods = ['paypal' => 'paypal']
            );
        $paymentMethodCollection->expects($this->any())
            ->method('getElements')
            ->willReturn([
                $paymentMethod = $this->getMockBuilder(PaymentMethodEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $this->hiddenMethodService->expects($this->once())
            ->method('isMethodHidden')
            ->willReturn(true);
        $paymentMethod->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([
                PevrPayeverIntegration::CUSTOM_FIELD_ACCEPT_FEE => false,
                PevrPayeverIntegration::CUSTOM_FIELD_FIXED_FEE => 0,
                PevrPayeverIntegration::CUSTOM_FIELD_VARIABLE_FEE => 1,
            ]);
        $paymentMethod->expects($this->once())
            ->method('getTranslated')
            ->willReturn(['name' => 'some-name']);
        $this->listener->onCheckoutConfirm($event);
    }

    public function testOnCheckoutConfirmCaseFixedFee()
    {
        /** @var MockObject|CheckoutConfirmPageLoadedEvent $event */
        $event = $this->getMockBuilder(CheckoutConfirmPageLoadedEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->once())
            ->method('getPage')
            ->willReturn(
                $page = $this->getMockBuilder(CheckoutConfirmPage::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $page->expects($this->once())
            ->method('getPaymentMethods')
            ->willReturn(
                $paymentMethodCollection = $this->getMockBuilder(PaymentMethodCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $event->expects($this->once())
            ->method('getSalesChannelContext')
            ->willReturn(
                $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->hiddenMethodService->expects($this->once())
            ->method('getCurrentHiddenMethodsFromSession')
            ->willReturn(
                $hiddenMethods = ['paypal' => 'paypal']
            );
        $paymentMethodCollection->expects($this->any())
            ->method('getElements')
            ->willReturn([
                $paymentMethod = $this->getMockBuilder(PaymentMethodEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $this->hiddenMethodService->expects($this->once())
            ->method('isMethodHidden')
            ->willReturn(true);
        $paymentMethod->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([
                PevrPayeverIntegration::CUSTOM_FIELD_ACCEPT_FEE => false,
                PevrPayeverIntegration::CUSTOM_FIELD_FIXED_FEE => 1,
                PevrPayeverIntegration::CUSTOM_FIELD_VARIABLE_FEE => 0,
            ]);
        $salesChannelContext->expects($this->once())
            ->method('getCurrency')
            ->willReturn(
                $currencyEntity = $this->getMockBuilder(CurrencyEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $currencyEntity->expects($this->once())
            ->method('getSymbol')
            ->willReturn('EUR');
        $paymentMethod->expects($this->once())
            ->method('getTranslated')
            ->willReturn(['name' => 'some-name']);
        $this->listener->onCheckoutConfirm($event);
    }

    public function testOnAccountEditOrder()
    {
        /** @var MockObject|CheckoutConfirmPageLoadedEvent $event */
        $event = $this->getMockBuilder(AccountEditOrderPageLoadedEvent::class)
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->any())
            ->method('getPage')
            ->willReturn(
                $page = $this->getMockBuilder(AccountEditOrderPage::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $page->expects($this->once())
            ->method('getOrder')
            ->willReturn(
                $orderEntity = $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $orderEntity->expects($this->once())
            ->method('getId')
            ->willReturn(
                $orderId = 'some-order-id'
            );
        $this->hiddenMethodService->expects($this->once())
            ->method('getCurrentHiddenMethods')
            ->willReturn(
                $hiddenMethodsRow = $this->getMockBuilder(HiddenMethodsEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $hiddenMethodsRow->expects($this->once())
            ->method('getHiddenMethods')
            ->willReturn(
                $hiddenMethods = '{"paypal":"paypal"}'
            );
        $this->hiddenMethodService->expects($this->once())
            ->method('getCurrentHiddenMethodsFromSession')
            ->willReturn(
                $hiddenMethodsFromSession = ['santander_invoice_de' => 'santander_invoice_de']
            );
        $page->expects($this->once())
            ->method('getPaymentMethods')
            ->willReturn(
                $paymentMethodCollection = $this->getMockBuilder(PaymentMethodCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $event->expects($this->once())
            ->method('getSalesChannelContext')
            ->willReturn(
                $salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $paymentMethodCollection->expects($this->any())
            ->method('getElements')
            ->willReturn([
                $paymentMethod = $this->getMockBuilder(PaymentMethodEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $this->hiddenMethodService->expects($this->once())
            ->method('isMethodHidden')
            ->willReturn(true);
        $paymentMethod->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([
                PevrPayeverIntegration::CUSTOM_FIELD_ACCEPT_FEE => false,
                PevrPayeverIntegration::CUSTOM_FIELD_FIXED_FEE => 1,
                PevrPayeverIntegration::CUSTOM_FIELD_VARIABLE_FEE => 1,
            ]);
        $salesChannelContext->expects($this->once())
            ->method('getCurrency')
            ->willReturn(
                $currencyEntity = $this->getMockBuilder(CurrencyEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $currencyEntity->expects($this->once())
            ->method('getSymbol')
            ->willReturn('EUR');
        $paymentMethod->expects($this->once())
            ->method('getTranslated')
            ->willReturn(['name' => 'some-name']);
        $this->listener->onAccountEditOrder($event);
    }
}
