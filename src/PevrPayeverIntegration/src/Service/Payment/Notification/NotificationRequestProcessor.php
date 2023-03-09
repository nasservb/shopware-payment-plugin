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

use Payever\ExternalIntegration\Core\Lock\LockInterface;
use Payever\ExternalIntegration\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\ExternalIntegration\Payments\Notification\NotificationHandlerInterface;
use Payever\ExternalIntegration\Payments\Notification\NotificationRequestProcessor as BaseProcessor;
use Payever\PayeverPayments\Service\Helper\SalesChannelContextHelper;
use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use Payever\PayeverPayments\Service\PayeverPayment;
use Payever\PayeverPayments\Service\Setting\SettingsServiceInterface;
use Psr\Log\LoggerInterface;

class NotificationRequestProcessor extends BaseProcessor
{
    public const NOTIFICATION_TYPE = 'raw_request';
    public const HEADER_SIGNATURE = 'X-PAYEVER-SIGNATURE';

    /** @var ClientFactory */
    private $apiClientFactory;

    /** @var SettingsServiceInterface */
    private $settingsService;

    /** @var SalesChannelContextHelper */
    private $salesChannelContextHelper;

    /**
     * @param NotificationHandlerInterface $handler
     * @param LockInterface $lock
     * @param LoggerInterface $logger
     * @param ClientFactory $apiClientFactory
     * @param SettingsServiceInterface $settingsService
     * @param SalesChannelContextHelper $salesChannelContextHelper
     */
    public function __construct(
        NotificationHandlerInterface $handler,
        LockInterface $lock,
        LoggerInterface $logger,
        ClientFactory $apiClientFactory,
        SettingsServiceInterface $settingsService,
        SalesChannelContextHelper $salesChannelContextHelper
    ) {
        parent::__construct($handler, $lock, $logger);
        $this->apiClientFactory = $apiClientFactory;
        $this->settingsService = $settingsService;
        $this->salesChannelContextHelper = $salesChannelContextHelper;
    }

    /**
     * {@inheritDoc}
     */
    protected function unserializePayload($payload)
    {
        $notificationRequestEntity = parent::unserializePayload($payload);
        $notificationRequestEntity->setNotificationType(self::NOTIFICATION_TYPE);
        $notificationRequestEntity->setNotificationTypesAvailable([self::NOTIFICATION_TYPE]);

        return $notificationRequestEntity;
    }

    /**
     * {@inheritDoc}
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function getRequestPayload()
    {
        $request = $this->salesChannelContextHelper->getRequest();
        $paymentId = $request->query->get(PayeverPayment::REQUEST_PARAMETER_PAYMENT_ID, '');
        $signature = $request->headers->get(self::HEADER_SIGNATURE);
        $salesChannelContext = $this->salesChannelContextHelper->getSalesChannelContext();
        $payload = $request->getContent();
        if ($signature) {
            $this->assertSignatureValid($paymentId, $signature);
        } else {
            $rawData = !empty($payload) ? json_decode($payload, true) : [];
            /** @var RetrievePaymentResultEntity $payeverPayment */
            $payeverPayment = $this->apiClientFactory
                ->getPaymentsApiClient($salesChannelContext->getSalesChannel()->getId())
                ->retrievePaymentRequest($paymentId)
                ->getResponseEntity()
                ->getResult();
            $notificationDateTime = is_array($rawData) && array_key_exists('created_at', $rawData)
                ? $rawData['created_at']
                : null;
            $payload = json_encode([
                'created_at' => $notificationDateTime,
                'data' => [
                    'payment' => $payeverPayment->toArray(),
                ],
            ]);
        }

        return $payload;
    }

    /**
     * @param string $paymentId
     * @param string $signature
     * @throws \Payever\PayeverPayments\Service\Setting\Exception\PayeverSettingsInvalidException
     * @throws \BadMethodCallException
     */
    private function assertSignatureValid(string $paymentId, string $signature): void
    {
        $settings = $this->settingsService->getSettings();
        $expectedSignature = hash_hmac(
            'sha256',
            $settings->getClientId() . $paymentId,
            $settings->getClientSecret()
        );
        if ($signature !== $expectedSignature) {
            throw new \BadMethodCallException('Notification rejected: invalid signature');
        }
    }
}
