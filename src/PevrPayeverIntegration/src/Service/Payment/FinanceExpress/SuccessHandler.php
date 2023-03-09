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

namespace Payever\PayeverPayments\Service\Payment\FinanceExpress;

use Payever\ExternalIntegration\Core\Lock\LockInterface;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\PayeverPayments\Service\PayeverPayment;
use Payever\PayeverPayments\Service\Payment\TransactionStatusService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SuccessHandler
{
    /**
     * @var PayeverPayment
     */
    protected $paymentHandler;

    /**
     * @var TransactionStatusService
     */
    protected $transactionStatusService;

    /**
     * @var CustomerHelper
     */
    protected $customerHelper;

    /**
     * @var OrderHelper
     */
    protected $orderHelper;

    /**
     * @var LockInterface
     */
    protected $locker;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param PayeverPayment $paymentHandler
     * @param TransactionStatusService $transactionStatusService
     * @param CustomerHelper $customerHelper
     * @param OrderHelper $orderHelper
     * @param LockInterface $locker
     * @param LoggerInterface $logger
     */
    public function __construct(
        PayeverPayment $paymentHandler,
        TransactionStatusService $transactionStatusService,
        CustomerHelper $customerHelper,
        OrderHelper $orderHelper,
        LockInterface $locker,
        LoggerInterface $logger
    ) {
        $this->paymentHandler = $paymentHandler;
        $this->transactionStatusService = $transactionStatusService;
        $this->customerHelper = $customerHelper;
        $this->orderHelper = $orderHelper;
        $this->locker = $locker;
        $this->logger = $logger;
    }

    /**
     * @param SalesChannelContext $context
     * @param string $paymentId
     * @return string|null
     * @throws \Exception
     */
    public function handle(SalesChannelContext $context, string $paymentId): ?string
    {
        $this->logger->debug(sprintf('Attempt to lock %s', $paymentId));
        $this->locker->acquireLock($paymentId, PayeverPayment::LOCK_TIMEOUT);
        $paymentResult = $this->paymentHandler->retrieveRequest($paymentId, $context);
        if (!$paymentResult) {
            $this->logger->warning('Unable to retrieve payment result entity');
            return null;
        }
        $payeverPaymentStatus = $paymentResult->getStatus();
        if (!$this->transactionStatusService->isSuccessfulPaymentStatus($payeverPaymentStatus)) {
            $this->logger->info(sprintf('Skip handling payever payment status %s', $payeverPaymentStatus));
            return null;
        }
        $order = $this->prepareOrder($context, $paymentResult);
        $this->transactionStatusService->persistTransactionStatus($context, $paymentResult);
        $this->locker->releaseLock($paymentId);
        $this->logger->debug(sprintf('Unlocked  %s', $paymentId));

        return $order->getId();
    }

    /**
     * @param SalesChannelContext $context
     * @param RetrievePaymentResultEntity $paymentResult
     * @return OrderEntity
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function prepareOrder(
        SalesChannelContext $context,
        RetrievePaymentResultEntity $paymentResult
    ): OrderEntity {
        $customer = $this->customerHelper->getCustomer($context, $paymentResult);
        $order = $this->orderHelper->getOrder($context, $customer, $paymentResult);
        // substitute reference to connect transaction with order properly
        $paymentResult->setReference($order->getOrderNumber());

        return $order;
    }
}
