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

use Payever\PayeverPayments\Service\Payment\Notification\NotificationRequestProcessor;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NotificationController extends StorefrontController
{
    /** @var NotificationRequestProcessor */
    private $notificationRequestProcessor;

    /**
     * @param NotificationRequestProcessor $notificationRequestProcessor
     */
    public function __construct(NotificationRequestProcessor $notificationRequestProcessor)
    {
        $this->notificationRequestProcessor = $notificationRequestProcessor;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route(
     *     "/payever/notification",
     *     name="payever.payment.notification",
     *     defaults={"csrf_protected"=false},
     *     methods={"POST"}
     * )
     *
     * @return JsonResponse
     */
    public function execute(): JsonResponse
    {
        $status = false;
        try {
            $notificationResult = $this->notificationRequestProcessor->processNotification();
            $status = !$notificationResult->isFailed();
            $data = ['message' => (string) $notificationResult];
        } catch (\Exception $e) {
            $data = ['message' => $e->getMessage(), 'code' => 0];
        }
        $data['status'] = $status;

        return $this->json($data, $status ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
    }
}
