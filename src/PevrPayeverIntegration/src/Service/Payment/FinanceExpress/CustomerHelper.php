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

use Payever\ExternalIntegration\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\PayeverPayments\Service\Generator\CustomerGenerator;
use Payever\PayeverPayments\Service\Helper\ConnectionHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CustomerHelper
{
    public const CUSTOM_FIELD_TRANSACTION_IDS = 'payever_fe_transaction_ids';

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var SalesChannelContextPersister
     */
    private $contextPersister;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ConnectionHelper
     */
    private $connectionHelper;

    /**
     * @var CustomerGenerator
     */
    private $customerGenerator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param EntityRepositoryInterface $customerRepository
     * @param SalesChannelContextPersister $contextPersister
     * @param EventDispatcherInterface $eventDispatcher
     * @param ConnectionHelper $connectionHelper
     * @param CustomerGenerator $customerGenerator
     * @param LoggerInterface $logger
     */
    public function __construct(
        EntityRepositoryInterface $customerRepository,
        SalesChannelContextPersister $contextPersister,
        EventDispatcherInterface $eventDispatcher,
        ConnectionHelper $connectionHelper,
        CustomerGenerator $customerGenerator,
        LoggerInterface $logger
    ) {
        $this->customerRepository = $customerRepository;
        $this->contextPersister = $contextPersister;
        $this->eventDispatcher = $eventDispatcher;
        $this->connectionHelper = $connectionHelper;
        $this->customerGenerator = $customerGenerator;
        $this->logger = $logger;
    }

    /**
     * @param SalesChannelContext $context
     * @param RetrievePaymentResultEntity $paymentResult
     * @return CustomerEntity
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getCustomer(
        SalesChannelContext $context,
        RetrievePaymentResultEntity $paymentResult
    ): CustomerEntity {
        $isNewCustomer = false;
        $customer = $context->getCustomer();
        $transactionId = $paymentResult->getId();
        if (!$customer) {
            $sql = <<<SQL
SELECT LOWER(HEX(id)) FROM customer
WHERE JSON_CONTAINS(custom_fields, JSON_ARRAY(:payever_transaction_id), :custom_field_name)
SQL;
            $params = [
                'payever_transaction_id' => $transactionId,
                'custom_field_name' => sprintf('$.%s', self::CUSTOM_FIELD_TRANSACTION_IDS),
            ];
            $statement = $this->connectionHelper->executeQuery($sql, $params);
            $customerId = $this->connectionHelper->fetchOne($statement);
            if ($customerId) {
                $customer = $this->customerRepository->search(
                    new Criteria([$customerId]),
                    $context->getContext()
                )->getEntities()->first();
            }
        }
        if (!$customer) {
            $isNewCustomer = true;
            $customer = $this->customerGenerator->generate($context, $paymentResult);
            $this->logger->info(sprintf('Generated customer %s', $customer->getId()));
            $newToken = $this->contextPersister->replace($context->getToken(), $context);
            $this->contextPersister->save(
                $newToken,
                [
                    'customerId' => $customer->getId(),
                ],
                $context->getSalesChannel()->getId(),
                $customer->getId()
            );
            $event = new CustomerLoginEvent($context, $customer, $newToken);
            $this->eventDispatcher->dispatch($event);
        }
        if (!$isNewCustomer) {
            $customFields = $customer->getCustomFields() ?? [];
            $transactionIds = $customFields[self::CUSTOM_FIELD_TRANSACTION_IDS] ?? [];
            $transactionIds[] = $transactionId;
            $customFields[self::CUSTOM_FIELD_TRANSACTION_IDS] = array_unique($transactionIds);
            $customer->setCustomFields($customFields);
            $this->customerRepository->update(
                [
                    [
                        'id' => $customer->getId(),
                        'customFields' => $customFields,
                    ]
                ],
                $context->getContext()
            );
        }

        return $customer;
    }
}
