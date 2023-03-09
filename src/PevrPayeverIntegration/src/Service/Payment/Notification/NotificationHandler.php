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

namespace Payever\PayeverPayments\Service\Payment\Notification;

use Payever\ExternalIntegration\Payments\Http\RequestEntity\NotificationRequestEntity;
use Payever\ExternalIntegration\Payments\Notification\NotificationHandlerInterface;
use Payever\ExternalIntegration\Payments\Notification\NotificationResult;
use Payever\PayeverPayments\Service\Helper\SalesChannelContextHelper;
use Payever\PayeverPayments\Service\Payment\TransactionStatusService;

class NotificationHandler implements NotificationHandlerInterface
{
    /** @var TransactionStatusService */
    private $transactionStatusService;

    /** @var SalesChannelContextHelper */
    private $salesChannelContextHelper;

    /**
     * @param TransactionStatusService $transactionStatusService
     * @param SalesChannelContextHelper $salesChannelContextHelper
     */
    public function __construct(
        TransactionStatusService $transactionStatusService,
        SalesChannelContextHelper $salesChannelContextHelper
    ) {
        $this->transactionStatusService = $transactionStatusService;
        $this->salesChannelContextHelper = $salesChannelContextHelper;
    }

    /**
     * @param NotificationRequestEntity $notification
     * @param NotificationResult $notificationResult
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     * @throws \Shopware\Core\System\StateMachine\Exception\IllegalTransitionException
     * @throws \Shopware\Core\System\StateMachine\Exception\StateMachineInvalidEntityIdException
     * @throws \Shopware\Core\System\StateMachine\Exception\StateMachineInvalidStateFieldException
     * @throws \Shopware\Core\System\StateMachine\Exception\StateMachineNotFoundException
     */
    public function handleNotification(
        NotificationRequestEntity $notification,
        NotificationResult $notificationResult
    ): void {
        $salesChannelContext = $this->salesChannelContextHelper->getSalesChannelContext();
        $retrievePaymentResultEntity = $notification->getPayment();
        $orderReference = $retrievePaymentResultEntity->getReference();
        $notificationDateTime = $notification->getCreatedAt();
        $notificationTimestamp = $notificationDateTime instanceof \DateTime
            ? $notificationDateTime->getTimestamp()
            : 0;
        $shouldRejectNotification = $this->transactionStatusService->shouldRejectNotification(
            $orderReference,
            $notificationTimestamp,
            $salesChannelContext
        );
        if ($shouldRejectNotification) {
            $notificationResult->addMessage('Notification rejected: newer notification already processed');

            return;
        }
        $this->transactionStatusService->persistTransactionStatus(
            $salesChannelContext,
            $retrievePaymentResultEntity
        );
        $this->transactionStatusService->updateNotificationTimestamp(
            $orderReference,
            $notificationTimestamp,
            $salesChannelContext
        );
        $notificationResult->addMessage('Payment state was updated');
    }
}
