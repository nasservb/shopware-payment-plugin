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
use Payever\ExternalIntegration\Payments\Http\RequestEntity\NotificationRequestEntity;
use Payever\ExternalIntegration\Payments\Notification\NotificationHandlerInterface;
use Payever\ExternalIntegration\Payments\Notification\NotificationResult;
use Payever\PayeverPayments\Service\Helper\SalesChannelContextHelper;
use Payever\PayeverPayments\Service\PayeverPayment;
use Payever\PayeverPayments\Service\Payment\TransactionStatusService;
use Psr\Log\LoggerInterface;

class NotificationHandler extends SuccessHandler implements NotificationHandlerInterface
{
    /**
     * @var SalesChannelContextHelper
     */
    private $salesChannelContextHelper;

    /**
     * @param PayeverPayment $paymentHandler
     * @param TransactionStatusService $transactionStatusService
     * @param CustomerHelper $customerHelper
     * @param OrderHelper $orderHelper
     * @param LockInterface $locker
     * @param LoggerInterface $logger
     * @param SalesChannelContextHelper $salesChannelContextHelper
     */
    public function __construct(
        PayeverPayment $paymentHandler,
        TransactionStatusService $transactionStatusService,
        CustomerHelper $customerHelper,
        OrderHelper $orderHelper,
        LockInterface $locker,
        LoggerInterface $logger,
        SalesChannelContextHelper $salesChannelContextHelper
    ) {
        parent::__construct(
            $paymentHandler,
            $transactionStatusService,
            $customerHelper,
            $orderHelper,
            $locker,
            $logger
        );
        $this->salesChannelContextHelper = $salesChannelContextHelper;
    }

    /**
     * @param NotificationRequestEntity $notification
     * @param NotificationResult $notificationResult
     * @throws \Doctrine\DBAL\DBALException
     */
    public function handleNotification(
        NotificationRequestEntity $notification,
        NotificationResult $notificationResult
    ): void {
        $paymentResult = $notification->getPayment();
        $payeverPaymentStatus = $paymentResult->getStatus();
        if (!$this->transactionStatusService->isSuccessfulPaymentStatus($payeverPaymentStatus)) {
            $notificationResult->addMessage(sprintf(
                'Skip handling payever payment status %s',
                $payeverPaymentStatus
            ));

            return;
        }
        $context = $this->salesChannelContextHelper->getSalesChannelContext();
        $this->prepareOrder($context, $paymentResult);
        $reference = $paymentResult->getReference();
        $notificationDateTime = $notification->getCreatedAt();
        $notificationTimestamp = $notificationDateTime instanceof \DateTime
            ? $notificationDateTime->getTimestamp()
            : 0;
        $shouldRejectNotification = $this->transactionStatusService->shouldRejectNotification(
            $reference,
            $notificationTimestamp,
            $context
        );
        if ($shouldRejectNotification) {
            $notificationResult->addMessage('Notification rejected: newer notification already processed');

            return;
        }
        $this->transactionStatusService->persistTransactionStatus($context, $paymentResult);
        $this->transactionStatusService->updateNotificationTimestamp(
            $reference,
            $notificationTimestamp,
            $context
        );
        $notificationResult->addMessage('Payment state was updated');
    }
}
