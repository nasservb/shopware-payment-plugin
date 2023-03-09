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

namespace Payever\PayeverPayments\Controller;

use Payever\PayeverPayments\Service\Payment\TransactionStatusService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Payever\PayeverPayments\Service\PayeverPayment;
use Shopware\Core\Checkout\Payment\Exception\CustomerCanceledAsyncPaymentException;
use Shopware\Core\Checkout\Payment\Exception\InvalidTransactionException;
use Shopware\Core\Checkout\Payment\Exception\PaymentProcessException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

class FinalizeController extends StorefrontController
{
    /** @var TransactionStatusService */
    private $transactionStatusService;

    /** @var AsynchronousPaymentHandlerInterface|PayeverPayment */
    private $paymentHandler;

    /**
     * @var OrderTransactionStateHandler
     */
    private $transactionStateHandler;

    /** @var EntityRepositoryInterface */
    private $orderRepository;

    /** @var RouterInterface */
    private $router;


    public function __construct(
        TransactionStatusService $transactionStatusService,
        AsynchronousPaymentHandlerInterface $paymentHandler,
        OrderTransactionStateHandler $transactionStateHandler,
        EntityRepositoryInterface $orderRepository,
        RouterInterface $router
    ) {
        $this->transactionStatusService = $transactionStatusService;
        $this->paymentHandler = $paymentHandler;
        $this->transactionStateHandler = $transactionStateHandler;
        $this->orderRepository = $orderRepository;
        $this->router = $router;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route(
     *     "/payever/payment/finalize-transaction/{transactionId}/{paymentId}",
     *     name="payever.payment.success",
     *     defaults={"csrf_protected"=false}, methods={"GET"}
     * )
     *
     * @param string $transactionId
     * @param string $paymentId
     * @param SalesChannelContext $salesChannelContext
     *
     * @return RedirectResponse
     */
    public function finalizeTransaction(
        string $transactionId,
        string $paymentId,
        Request $request,
        SalesChannelContext $salesChannelContext
    ): RedirectResponse {
        $orderTransaction = $this->transactionStatusService->getOrderTransactionById(
            $salesChannelContext->getContext(),
            $transactionId
        );

        $order = $orderTransaction->getOrder();
        if ($order === null) {
            $order = $this->getOrder($orderTransaction->getOrderId());
        }

        if ($order === null) {
            throw new InvalidTransactionException($orderTransaction->getId());
        }

        $paymentTransactionStruct = new AsyncPaymentTransactionStruct($orderTransaction, $order, '');
        $changedPayment = $request->query->getBoolean('changedPayment');
        $orderId = $order->getId();

        $finishUrl = $this->router->generate('frontend.checkout.finish.page', [
            'orderId' => $orderId,
            'changedPayment' => $changedPayment,
        ]);

        // Define parameters
        $request->query->set(PayeverPayment::REQUEST_PARAMETER_PAYMENT_ID, $paymentId);
        $request->query->set(PayeverPayment::REQUEST_PARAMETER_TYPE, PayeverPayment::CALLBACK_TYPE_SUCCESS);

        try {
            // Forwarding to payment handler.
            $this->paymentHandler->finalize($paymentTransactionStruct, $request, $salesChannelContext);
        } catch (PaymentProcessException $paymentProcessException) {
            // Redirecting to confirm page.
            $finishUrl = $this->redirectToConfirmPageWorkflow(
                $paymentProcessException,
                $salesChannelContext->getContext(),
                $orderId
            );
        }

        return new RedirectResponse($finishUrl);
    }

    private function redirectToConfirmPageWorkflow(
        PaymentProcessException $paymentProcessException,
        Context $context,
        string $orderId
    ): string {
        $errorUrl = $this->router->generate('frontend.account.edit-order.page', ['orderId' => $orderId]);

        if ($paymentProcessException instanceof CustomerCanceledAsyncPaymentException) {
            $this->transactionStateHandler->cancel(
                $paymentProcessException->getOrderTransactionId(),
                $context
            );
            $urlQuery = \parse_url($errorUrl, \PHP_URL_QUERY) ? '&' : '?';

            return \sprintf('%s%serror-code=%s', $errorUrl, $urlQuery, $paymentProcessException->getErrorCode());
        }

        $transactionId = $paymentProcessException->getOrderTransactionId();
        $this->transactionStateHandler->fail(
            $transactionId,
            $context
        );
        $urlQuery = \parse_url($errorUrl, \PHP_URL_QUERY) ? '&' : '?';

        return \sprintf('%s%serror-code=%s', $errorUrl, $urlQuery, $paymentProcessException->getErrorCode());
    }

    /**
     * Get Order.
     *
     * @param string $orderId
     *
     * @return OrderEntity
     */
    private function getOrder(string $orderId)
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('id', $orderId)
        );
        $criteria->addAssociation('transactions');

        /** @var OrderEntity[] $entities */
        return $this->orderRepository
            ->search($criteria, Context::createDefaultContext())
            ->first();
    }
}
