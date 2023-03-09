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

use Payever\PayeverPayments\Service\Management\OrderTotalsManager;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentTotalsController extends AbstractController
{
    /** @var EntityRepositoryInterface */
    private $orderRepository;

    /**
     * @var OrderTotalsManager
     */
    private $totalsManager;

    /**
     * @param EntityRepositoryInterface $orderRepository
     * @param OrderTotalsManager $totalsManager
     */
    public function __construct(
        EntityRepositoryInterface $orderRepository,
        OrderTotalsManager $totalsManager
    ) {
        $this->orderRepository = $orderRepository;
        $this->totalsManager = $totalsManager;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/v{version}/_action/payever/data/get-payment-totals",
     *     name="api.action.payever.payment-totals.legacy",
     *     methods={"POST"}
     * )
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getPaymentTotalsLegacy(Request $request): JsonResponse
    {
        return $this->getPaymentTotals($request);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/payever/data/get-payment-totals",
     *     name="api.action.payever.payment-totals",
     *     methods={"POST"}
     * )
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getPaymentTotals(Request $request): JsonResponse
    {
        try {
            $orderId = $request->get('orderId');
            $order = $this->getOrder($orderId);

            $totals = $this->totalsManager->getTotals($order);
            if (!$totals) {
                return $this->json([
                    'amount' => $order->getAmountTotal(),
                    'captured' => 0,
                    'cancelled' => 0,
                    'refunded' => 0,
                    'availableForCapturing' => $order->getAmountTotal(),
                    'availableForCancelling' => 0,
                    'availableForRefunding' => 0,
                    'isManual' => false
                ]);
            }

            return $this->json([
                'amount' => $order->getAmountTotal(),
                'captured' => $totals->getCapturedTotal(),
                'cancelled' => $totals->getCancelledTotal(),
                'refunded' => $totals->getRefundedTotal(),
                'availableForCapturing' => $this->totalsManager->getAvailableForCapturing($order),
                'availableForCancelling' => $this->totalsManager->getAvailableForCancelling($order),
                'availableForRefunding' => $this->totalsManager->getAvailableForRefunding($order),
                'isManual' => $totals->isManual()
            ]);
        } catch (\Exception $exception) {
            return $this->json(
                ['status' => false, 'message' => $exception->getMessage(), 'code' => 0],
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    /**
     * Get Order.
     *
     * @param string $orderId
     *
     * @return OrderEntity
     * @throws \Exception
     */
    private function getOrder(string $orderId)
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('id', $orderId)
        );

        /** @var OrderEntity[] $entities */
        $entities = $this->orderRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->getElements();

        foreach ($entities as $entity) {
            return $entity;
        }

        throw new \Exception('Order is not found: ' . $orderId);
    }
}
