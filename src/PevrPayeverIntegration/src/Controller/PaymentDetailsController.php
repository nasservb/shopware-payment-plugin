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

use Payever\ExternalIntegration\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\ExternalIntegration\Payments\Http\ResponseEntity\RetrievePaymentResponse;
use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PaymentDetailsController extends AbstractController
{
    /** @var ClientFactory */
    private $apiClientFactory;

    /**
     * @param ClientFactory $apiClientFactory
     */
    public function __construct(ClientFactory $apiClientFactory)
    {
        $this->apiClientFactory = $apiClientFactory;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/v{version}/_action/payever/data/get-payment-details",
     *     name="api.action.payever.payment-details.legacy",
     *     methods={"POST"}
     * )
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getPaymentDetailsLegacy(Request $request): JsonResponse
    {
        return $this->getPaymentDetails($request);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/payever/data/get-payment-details",
     *     name="api.action.payever.payment-details",
     *     methods={"POST"}
     * )
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getPaymentDetails(Request $request): JsonResponse
    {
        try {
            $paymentId = $request->get('paymentId');
            $salesChannelId = $request->get('salesChannelId');

            $response = $this->apiClientFactory
                ->getPaymentsApiClient($salesChannelId)
                ->retrievePaymentRequest($paymentId);

            /** @var RetrievePaymentResponse $getTransactionEntity */
            $entity = $response->getResponseEntity();

            /** @var RetrievePaymentResultEntity $getTransactionResult */
            $result = $entity->getResult();

            return $this->json($result->toArray());
        } catch (\Exception $exception) {
            return $this->json(
                ['status' => false, 'message' => $exception->getMessage(), 'code' => 0],
                Response::HTTP_BAD_REQUEST
            );
        }
    }
}
