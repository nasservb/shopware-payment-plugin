<?php

namespace Payever\PayeverPayments\tests\unit\Service\Payment\FinanceExpress;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\ResultStatement;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\PayeverPayments\Service\Generator\OrderGenerator;
use Payever\PayeverPayments\Service\Helper\ConnectionHelper;
use Payever\PayeverPayments\Service\Payment\FinanceExpress\OrderHelper;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class OrderHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $orderTransactionRepository;

    /**
     * @var MockObject|ConnectionHelper
     */
    private $connectionHelper;

    /**
     * @var MockObject|OrderGenerator
     */
    private $orderGenerator;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var OrderHelper
     */
    private $helper;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->orderTransactionRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->connectionHelper = $this->getMockBuilder(ConnectionHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->orderGenerator = $this->getMockBuilder(OrderGenerator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->helper = new OrderHelper(
            $this->orderTransactionRepository,
            $this->connectionHelper,
            $this->orderGenerator,
            $this->logger
        );
    }

    public function testGetOrder()
    {
        /** @var MockObject|SalesChannelContext $context */
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|CustomerEntity $customer */
        $customer = $this->getMockBuilder(CustomerEntity::class)
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
            ->willReturn('some-order-transaction-uuid');
        $context->expects($this->any())
            ->method('getContext')
            ->willReturn(
                $this->getMockBuilder(Context::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->orderTransactionRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->orderGenerator->expects($this->once())
            ->method('generate')
            ->willReturn(
                $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->assertNotEmpty($this->helper->getOrder($context, $customer, $paymentResult));
    }

    public function testGetOrderCaseExistingOrder()
    {
        /** @var MockObject|SalesChannelContext $context */
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|CustomerEntity $customer */
        $customer = $this->getMockBuilder(CustomerEntity::class)
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
            ->willReturn('some-order-transaction-uuid');
        $context->expects($this->any())
            ->method('getContext')
            ->willReturn(
                $this->getMockBuilder(Context::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->orderTransactionRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn(
                $entityCollection = $this->getMockBuilder(EntityCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entityCollection->expects($this->once())
            ->method('first')
            ->willReturn(
                $transaction = $this->getMockBuilder(OrderTransactionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $transaction->expects($this->once())
            ->method('getOrder')
            ->willReturn(
                $this->getMockBuilder(OrderEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->orderGenerator->expects($this->never())
            ->method('generate');
        $this->assertNotEmpty($this->helper->getOrder($context, $customer, $paymentResult));
    }
}
