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

use Payever\PayeverPayments\Service\PayeverPayment;
use Payever\PayeverPayments\Service\Payment\FinanceExpress\FailureHandler;
use Payever\PayeverPayments\Service\Payment\FinanceExpress\SuccessHandler;
use Payever\PayeverPayments\Service\Payment\Notification\NotificationRequestProcessor;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class FinanceExpressController extends StorefrontController
{
    private const FETCH_MODE_HEADER = 'Sec-Fetch-Dest';

    /**
     * @var SuccessHandler
     */
    private $successHandler;

    /**
     * @var FailureHandler
     */
    private $failureHandler;

    /**
     * @var NotificationRequestProcessor
     */
    private $notificationRequestProcessor;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param SuccessHandler $successHandler
     * @param FailureHandler $failureHandler
     * @param NotificationRequestProcessor $notificationRequestProcessor
     * @param LoggerInterface $logger
     */
    public function __construct(
        SuccessHandler $successHandler,
        FailureHandler $failureHandler,
        NotificationRequestProcessor $notificationRequestProcessor,
        LoggerInterface $logger
    ) {
        $this->successHandler = $successHandler;
        $this->failureHandler = $failureHandler;
        $this->notificationRequestProcessor = $notificationRequestProcessor;
        $this->logger = $logger;
    }

    /**
     * Success url in payever widget config: %baseUrl%/payever/finance-express/success?paymentId=--PAYMENT-ID--
     *
     * @Route(
     *     "/payever/finance-express/success",
     *     name="payever.finance_express.success",
     *     defaults={"csrf_protected"=false},
     *     methods={"GET"}
     * )
     * @param Request $request
     * @param SalesChannelContext $context
     * @return Response
     * @throws \Exception
     */
    public function success(Request $request, SalesChannelContext $context): Response
    {
        $paymentId = $this->getPaymentId($request);
        $this->logger->info(sprintf('Hit finance-express/success for payment %s', $paymentId));
        try {
            $orderId = $this->successHandler->handle($context, $paymentId);
            if ($orderId) {
                return $this->redirectToRoute('frontend.checkout.finish.page', ['orderId' => $orderId]);
            }
        } catch (\Exception $exception) {
            $this->logger->warning($exception);
        }
        $this->logger->info('Forwarding to failure action');

        return $this->failure($request, $context);
    }

    /**
     * Pending url in payever widget config: %baseUrl%/payever/finance-express/pending?paymentId=--PAYMENT-ID--
     *
     * @Route(
     *     "/payever/finance-express/pending",
     *     name="payever.finance_express.pending",
     *     defaults={"csrf_protected"=false},
     *     methods={"GET"}
     * )
     * @param Request $request
     * @param SalesChannelContext $context
     * @return Response
     * @throws \Exception
     */
    public function pending(Request $request, SalesChannelContext $context): Response
    {
        $this->logger->info('Hit finance-express/pending');

        return $this->success($request, $context);
    }

    /**
     * Cancel url in payever widget config: %baseUrl%/payever/finance-express/cancel?paymentId=--PAYMENT-ID--
     *
     * @Route(
     *     "/payever/finance-express/cancel",
     *     name="payever.finance_express.cancel",
     *     defaults={"csrf_protected"=false},
     *     methods={"GET"}
     * )
     * @param Request $request
     * @param SalesChannelContext $context
     * @return Response
     */
    public function cancel(Request $request, SalesChannelContext $context): Response
    {
        $paymentId = $this->getPaymentId($request);
        $this->logger->info(sprintf('Hit finance-express/cancel for payment %s', $paymentId));

        return $this->handleFailure(
            $context,
            $paymentId,
            $this->trans('payever.paymentCancelled'),
            $request->headers->get(self::FETCH_MODE_HEADER)
        );
    }

    /**
     * Failure url in payever widget config: %baseUrl%/payever/finance-express/failure?paymentId=--PAYMENT-ID--
     *
     * @Route(
     *     "/payever/finance-express/failure",
     *     name="payever.finance_express.failure",
     *     defaults={"csrf_protected"=false},
     *     methods={"GET"}
     * )
     * @param Request $request
     * @param SalesChannelContext $context
     * @return Response
     */
    public function failure(Request $request, SalesChannelContext $context): Response
    {
        $paymentId = $this->getPaymentId($request);
        $this->logger->info(sprintf('Hit finance-express/failure for payment %s', $paymentId));

        return $this->handleFailure(
            $context,
            $paymentId,
            $this->trans('payever.paymentFailure'),
            $request->headers->get(self::FETCH_MODE_HEADER)
        );
    }

    /**
     * Notice url in payever widget config: %baseUrl%/payever/finance-express/notice?paymentId=--PAYMENT-ID--
     *
     * @Route(
     *     "/payever/finance-express/notice",
     *     name="payever.finance_express.notice",
     *     defaults={"csrf_protected"=false},
     *     methods={"POST"}
     * )
     * @param Request $request
     * @return JsonResponse
     */
    public function notice(Request $request): JsonResponse
    {
        $paymentId = $this->getPaymentId($request);
        $this->logger->info(sprintf('Hit finance-express/notice for payment %s', $paymentId));
        $result = false;
        try {
            $notificationResult = $this->notificationRequestProcessor->processNotification();
            $result = !$notificationResult->isFailed();
            $data = ['message' => (string) $notificationResult];
        } catch (\Exception $exception) {
            $data = ['message' => $exception->getMessage()];
        }
        $data['status'] = $result;

        return $this->json($data, $result ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
    }

    /**
     * @param Request $request
     * @return string
     */
    private function getPaymentId(Request $request): string
    {
        return $request->query->get(PayeverPayment::REQUEST_PARAMETER_PAYMENT_ID, '');
    }

    /**
     * @param SalesChannelContext $context
     * @param string $paymentId
     * @param string $flash
     * @param string|null $fetchHeader
     * @return Response
     */
    private function handleFailure(
        SalesChannelContext $context,
        string $paymentId,
        string $flash,
        string $fetchHeader = null
    ): Response {
        if ('iframe' === $fetchHeader) {
            return new Response('', Response::HTTP_NO_CONTENT);
        }
        $this->addFlash('danger', $flash);

        try {
            $seoUrlPath = $this->failureHandler->getSeoPath($context, $paymentId);
            if ($seoUrlPath) {
                return $this->redirect($seoUrlPath);
            }
            $productId = $this->failureHandler->getProductId($context, $paymentId);
            if ($productId) {
                return $this->redirectToRoute('frontend.detail.page', ['productId' => $productId]);
            }
        } catch (\Exception $exception) {
            $this->logger->notice($exception);
        }

        return $this->redirectToRoute('frontend.home.page');
    }
}
