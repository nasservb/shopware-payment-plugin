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

namespace Payever\PayeverPayments\Controller;

use Payever\ExternalIntegration\Payments\Action\ActionDeciderInterface;
use Payever\PayeverPayments\Service\Payment\PaymentActionService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentActionController extends AbstractController
{
    const ACTION_SHIP_GOODS = 'shippingGoods';
    const ACTION_REFUND_ITEM = 'refundItem';
    const ACTION_CANCEL_ITEM = 'cancelItem';

    /** @var PaymentActionService */
    private $payeverTriggersHandler;

    /** @var EntityRepositoryInterface */
    private $transactionRepository;

    /**
     * @param PaymentActionService $payeverTriggersHandler
     * @param EntityRepositoryInterface $transactionRepository
     */
    public function __construct(
        PaymentActionService $payeverTriggersHandler,
        EntityRepositoryInterface $transactionRepository
    ) {
        $this->payeverTriggersHandler = $payeverTriggersHandler;
        $this->transactionRepository = $transactionRepository;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/v{version}/_action/payever/{action}", name="api.action.payever.payment.legacy", methods={"POST"})
     *
     * @param Request $request
     * @param Context $context
     *
     * @return JsonResponse
     *
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    public function handleRequestLegacy(Request $request, Context $context): JsonResponse
    {
        return $this->handleRequest($request, $context);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route("/api/_action/payever/{action}", name="api.action.payever.payment", methods={"POST"})
     *
     * @param Request $request
     * @param Context $context
     *
     * @return JsonResponse
     *
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function handleRequest(Request $request, Context $context): JsonResponse
    {
        $action = $request->get('action');
        $transaction = $request->get('transaction');

        if (empty($transaction)) {
            return $this->json(
                ['status' => false, 'message' => 'missing order transaction id'],
                Response::HTTP_NOT_FOUND
            );
        }

        $criteria = new Criteria([$transaction]);
        $criteria->addAssociation('order')
            ->addAssociation('order.deliveries')
            ->addAssociation('order.deliveries.shippingMethod');

        /** @var null|OrderTransactionEntity $orderTransaction */
        $orderTransaction = $this->transactionRepository->search($criteria, $context)->first();

        if (null === $orderTransaction) {
            return $this->json(
                ['status' => false, 'message' => 'no order transaction found'],
                Response::HTTP_NOT_FOUND
            );
        }

        $this->payeverTriggersHandler->orderEventLock = true;

        try {
            switch ($action) {
                case ActionDeciderInterface::ACTION_CANCEL:
                    $amount = (float) $request->get('amount');
                    $this->payeverTriggersHandler->cancelTransaction($orderTransaction, $amount, true);
                    break;
                case ActionDeciderInterface::ACTION_SHIPPING_GOODS:
                    $amount = (float) $request->get('amount');
                    $this->payeverTriggersHandler->shippingTransaction($orderTransaction, $amount, true);
                    break;
                case ActionDeciderInterface::ACTION_RETURN:
                case ActionDeciderInterface::ACTION_REFUND:
                    $amount = (float) $request->get('amount');
                    $this->payeverTriggersHandler->refundTransaction($orderTransaction, $amount, true);
                    break;
                case self::ACTION_REFUND_ITEM:
                    $items = array_filter($request->get('items'), function ($item) {
                        return ($item > 0);
                    });

                    if (empty($items)) {
                        throw new \UnexpectedValueException('Items to refund not selected.');
                    }

                    $this->payeverTriggersHandler->refundItemTransaction(
                        $orderTransaction,
                        $items
                    );

                    break;
                case self::ACTION_SHIP_GOODS:
                    $items = array_filter($request->get('items'), function ($item) {
                        return ($item > 0);
                    });

                    if (empty($items)) {
                        throw new \UnexpectedValueException('No items to perform action.');
                    }

                    $this->payeverTriggersHandler->shipGoodsTransaction(
                        $orderTransaction,
                        $items
                    );

                    break;
                case self::ACTION_CANCEL_ITEM:
                    $items = array_filter($request->get('items'), function ($item) {
                        return ($item > 0);
                    });

                    if (empty($items)) {
                        throw new \UnexpectedValueException('Items for cancelling aren\'t selected.');
                    }

                    $this->payeverTriggersHandler->cancelItemTransaction(
                        $orderTransaction,
                        $items
                    );

                    break;
                default:
                    throw new \UnexpectedValueException(sprintf('Unknown payment action %s', $action));
            }
        } catch (\Exception $exception) {
            return $this->json(
                ['status' => false, 'message' => $exception->getMessage(), 'code' => 0],
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->payeverTriggersHandler->orderEventLock = false;

        return $this->json(['status' => true]);
    }
}
