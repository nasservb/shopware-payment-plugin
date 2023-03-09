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
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;

class CancelPaymentController extends StorefrontController
{
    /** @var TransactionStatusService */
    private $transactionStatusService;

    /**
     * @param TransactionStatusService $transactionStatusService
     */
    public function __construct(TransactionStatusService $transactionStatusService)
    {
        $this->transactionStatusService = $transactionStatusService;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route(
     *     "/payever/cancel/order/{transactionId}",
     *     name="payever.payment.cancel",
     *     defaults={"csrf_protected"=false}, methods={"GET"}
     * )
     *
     * @param string $transactionId
     * @param SalesChannelContext $salesChannelContext
     *
     * @return RedirectResponse
     */
    public function cancel(string $transactionId, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        $this->transactionStatusService->cancelOrderTransaction($salesChannelContext->getContext(), $transactionId);
        $orderTransaction = $this->transactionStatusService->getOrderTransactionById(
            $salesChannelContext->getContext(),
            $transactionId
        );
        $this->addFlash('danger', $this->trans('payever.paymentCancelled'));

        return $this->redirectToRoute(
            'frontend.account.edit-order.page',
            ['orderId' => $orderTransaction->getOrderId()]
        );
    }
}
