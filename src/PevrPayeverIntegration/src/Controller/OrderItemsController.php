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

use Payever\PayeverPayments\Service\Management\OrderItemsManager;
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

class OrderItemsController extends AbstractController
{
    /** @var EntityRepositoryInterface */
    private $orderRepository;

    /**
     * @var OrderItemsManager
     */
    private $orderItemsManager;

    /**
     * @param EntityRepositoryInterface $orderRepository
     * @param OrderItemsManager $orderItemsManager
     */
    public function __construct(
        EntityRepositoryInterface $orderRepository,
        OrderItemsManager $orderItemsManager
    ) {
        $this->orderRepository = $orderRepository;
        $this->orderItemsManager = $orderItemsManager;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/v{version}/_action/payever/data/get-order-items",
     *     name="api.action.payever.order-items.legacy",
     *     methods={"POST"}
     * )
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getOrderItemsLegacy(Request $request): JsonResponse
    {
        return $this->getOrderItems($request);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/payever/data/get-order-items",
     *     name="api.action.payever.order-items",
     *     methods={"POST"}
     * )
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getOrderItems(Request $request): JsonResponse
    {
        try {
            $orderId = $request->get('orderId');

            return $this->json($this->orderItemsManager->getItems($orderId));
        } catch (\Exception $exception) {
            return $this->json(
                ['status' => false, 'message' => $exception->getMessage(), 'code' => 0],
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}
