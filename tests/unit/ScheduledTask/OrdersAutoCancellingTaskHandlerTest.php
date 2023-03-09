<?php

namespace Payever\PayeverPayments\tests\unit\ScheduledTask;

use Payever\PayeverPayments\ScheduledTask\OrdersAutoCancellingTaskHandler;
use Payever\PayeverPayments\Service\Payment\PaymentOptionsService;
use Payever\PayeverPayments\Service\Payment\TransactionStatusService;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class OrdersAutoCancellingTaskHandlerTest extends \PHPUnit\Framework\TestCase
{
    /** @var MockObject|EntityRepositoryInterface */
    private $scheduledTaskRepository;

    /** @var MockObject|TransactionStatusService */
    private $transactionStatusService;

    /** @var MockObject|PaymentOptionsService */
    private $paymentOptionsHandler;

    /** @var MockObject|LoggerInterface */
    private $logger;

    /** @var OrdersAutoCancellingTaskHandler */
    private $handler;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->scheduledTaskRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->transactionStatusService = $this->getMockBuilder(TransactionStatusService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->paymentOptionsHandler = $this->getMockBuilder(PaymentOptionsService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->handler = new OrdersAutoCancellingTaskHandler(
            $this->scheduledTaskRepository,
            $this->transactionStatusService,
            $this->paymentOptionsHandler,
            $this->logger
        );
    }

    public function testGetHandledMessages()
    {
        $this->assertNotEmpty($this->handler->getHandledMessages());
    }

    public function testRun()
    {
        $this->paymentOptionsHandler->expects($this->once())
            ->method('getAllPaymentOptionIds')
            ->willReturn([]);
        $this->transactionStatusService->expects($this->once())
            ->method('getNotFinishedTransactions')
            ->willReturn(
                $searchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $searchResult->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $notFinishedTransaction = $this->getMockBuilder(OrderTransactionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $notFinishedTransaction->expects($this->once())
            ->method('getId')
            ->willReturn('some-id');
        $this->transactionStatusService->expects($this->once())
            ->method('cancelOrderTransaction');
        $this->handler->run();
    }

    public function testRunCaseException()
    {
        $this->paymentOptionsHandler->expects($this->once())
            ->method('getAllPaymentOptionIds')
            ->willThrowException(new \Exception());
        $this->logger->expects($this->once())
            ->method('warning');
        $this->handler->run();
    }
}
