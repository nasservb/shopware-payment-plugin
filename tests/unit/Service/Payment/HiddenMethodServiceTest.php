<?php

namespace Payever\PayeverPayments\tests\unit\Service\Payment;

use Payever\ExternalIntegration\Payments\Enum\PaymentMethod;
use Payever\PayeverPayments\PevrPayeverIntegration;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Payever\PayeverPayments\Service\Payment\HiddenMethodService;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class HiddenMethodServiceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var EntityRepositoryInterface
     */
    private $hiddenMethodsRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var HiddenMethodService
     */
    private $service;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->hiddenMethodsRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->service = new HiddenMethodService($this->session, $this->hiddenMethodsRepository, $this->logger);
    }

    public function testIsMethodHidden()
    {
        /** @var MockObject|PaymentMethodEntity $paymentMethod */
        $paymentMethod = $this->getMockBuilder(PaymentMethodEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $paymentMethod->expects($this->once())
            ->method('getCustomFields')
            ->willReturn([PevrPayeverIntegration::CUSTOM_FIELD_METHOD_CODE => 'paypal']);
        $hiddenMethods = ['paypal' => 'paypal'];
        $this->assertTrue($this->service->isMethodHidden($paymentMethod, $hiddenMethods));
    }

    public function testIsMethodHiddenCaseNoCustomFields()
    {
        /** @var MockObject|PaymentMethodEntity $paymentMethod */
        $paymentMethod = $this->getMockBuilder(PaymentMethodEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $paymentMethod->expects($this->once())
            ->method('getCustomFields')
            ->willReturn(null);
        $hiddenMethods = ['paypal' => 'paypal'];
        $this->assertFalse($this->service->isMethodHidden($paymentMethod, $hiddenMethods));
    }

    public function testProcessFailedMethodByCode()
    {
        $this->session->expects($this->once())
            ->method('set');
        $this->service->processFailedMethodByCode(PaymentMethod::METHOD_SANTANDER_DE_FACTORING, 'some-order-id');
    }
}
