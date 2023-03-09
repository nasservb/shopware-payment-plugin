<?php

namespace Payever\PayeverPayments\tests\unit\Service\Payment\FinanceExpress;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\ResultStatement;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\PayeverPayments\Service\Generator\CustomerGenerator;
use Payever\PayeverPayments\Service\Helper\ConnectionHelper;
use Payever\PayeverPayments\Service\Payment\FinanceExpress\CustomerHelper;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CustomerHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var MockObject|SalesChannelContextPersister
     */
    private $contextPersister;

    /**
     * @var MockObject|EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var MockObject|ConnectionHelper
     */
    private $connectionHelper;

    /**
     * @var MockObject|CustomerGenerator
     */
    private $customerGenerator;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var CustomerHelper
     */
    private $helper;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->customerRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->contextPersister = $this->getMockBuilder(SalesChannelContextPersister::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->eventDispatcher = $this->getMockBuilder(EventDispatcherInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->connectionHelper = $this->getMockBuilder(ConnectionHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->customerGenerator = $this->getMockBuilder(CustomerGenerator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->helper = new CustomerHelper(
            $this->customerRepository,
            $this->contextPersister,
            $this->eventDispatcher,
            $this->connectionHelper,
            $this->customerGenerator,
            $this->logger
        );
    }

    public function testGetCustomer()
    {
        /** @var MockObject|SalesChannelContext $context */
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|RetrievePaymentResultEntity $paymentResult */
        $paymentResult = $this->getMockBuilder(RetrievePaymentResultEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->connectionHelper->expects($this->once())
            ->method('executeQuery')
            ->willReturn(
                $this->getMockBuilder(
                    class_exists('Doctrine\DBAL\Driver\Result')
                        ? Result::class
                        : ResultStatement::class
                )
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->connectionHelper->expects($this->once())
            ->method('fetchOne')
            ->willReturn('some-customer-uuid');
        $context->expects($this->any())
            ->method('getContext')
            ->willReturn(
                $this->getMockBuilder(Context::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->customerGenerator->expects($this->once())
            ->method('generate')
            ->willReturn(
                $customer = $this->getMockBuilder(CustomerEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $customer->expects($this->any())
            ->method('getId')
            ->willReturn('some-customer-uuid');
        $this->contextPersister->expects($this->once())
            ->method('replace')
            ->willReturn('some-token');
        $context->expects($this->once())
            ->method('getSalesChannel')
            ->willReturn(
                $this->getMockBuilder(SalesChannelEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->contextPersister->expects($this->once())
            ->method('save');
        $this->assertNotEmpty($this->helper->getCustomer($context, $paymentResult));
    }
}
