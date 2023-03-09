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
use Payever\PayeverPayments\PevrPayeverIntegration;
use Payever\PayeverPayments\Service\Generator\OrderGenerator;
use Payever\PayeverPayments\Service\Helper\ConnectionHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class OrderHelper
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderTransactionRepository;

    /**
     * @var ConnectionHelper
     */
    private $connectionHelper;

    /**
     * @var OrderGenerator
     */
    private $orderGenerator;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param EntityRepositoryInterface $orderTransactionRepository
     * @param ConnectionHelper $connectionHelper
     * @param OrderGenerator $orderGenerator
     * @param LoggerInterface $logger
     */
    public function __construct(
        EntityRepositoryInterface $orderTransactionRepository,
        ConnectionHelper $connectionHelper,
        OrderGenerator $orderGenerator,
        LoggerInterface $logger
    ) {
        $this->orderTransactionRepository = $orderTransactionRepository;
        $this->connectionHelper = $connectionHelper;
        $this->orderGenerator = $orderGenerator;
        $this->logger = $logger;
    }

    /**
     * @param SalesChannelContext $context
     * @param CustomerEntity $customer
     * @param RetrievePaymentResultEntity $paymentResult
     * @return OrderEntity
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getOrder(
        SalesChannelContext $context,
        CustomerEntity $customer,
        RetrievePaymentResultEntity $paymentResult
    ): OrderEntity {
        $order = null;
        $sql = <<<SQL
SELECT LOWER(HEX(id)) FROM order_transaction
WHERE JSON_CONTAINS(custom_fields, JSON_QUOTE(:payever_transaction_id), :custom_field_name)
SQL;
        $params = [
            'payever_transaction_id' => $paymentResult->getId(),
            'custom_field_name' => sprintf('$.%s', PevrPayeverIntegration::CUSTOM_FIELD_TRANSACTION_ID),
        ];
        $statement = $this->connectionHelper->executeQuery($sql, $params);
        $transactionId = $this->connectionHelper->fetchOne($statement);
        if ($transactionId) {
            /** @var OrderTransactionEntity|null $transaction */
            $transaction = $this->orderTransactionRepository->search(
                (new Criteria([$transactionId]))->addAssociation('order'),
                $context->getContext()
            )->getEntities()->first();
            if ($transaction instanceof OrderTransactionEntity) {
                $order = $transaction->getOrder();
            }
        }
        if (!$order) {
            $order = $this->orderGenerator->generate($context, $customer, $paymentResult);
            $this->logger->info(sprintf('Generated order %s', $order->getId()));
        }

        return $order;
    }
}
