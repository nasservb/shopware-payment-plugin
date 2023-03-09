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

use Payever\PayeverPayments\Service\Management\ExportManager;
use Payever\PayeverPayments\Service\Management\ImportManager;
use Payever\PayeverPayments\Service\Management\SubscriptionManager;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProductsAndInventoryController extends \Symfony\Bundle\FrameworkBundle\Controller\AbstractController
{
    private const KEY_IS_ACTIVE = 'isActive';
    private const KEY_SUCCESS = 'success';
    private const KEY_BATCH_COUNT = 'batchCount';
    private const KEY_ERRORS = 'errors';

    /**
     * @var SubscriptionManager
     */
    private $subscriptionManager;

    /**
     * @var ExportManager
     */
    private $exportManager;

    /**
     * @var ImportManager
     */
    private $importManager;

    /**
     * @param SubscriptionManager $subscriptionManager
     * @param ExportManager $exportManager
     * @param ImportManager $importManager
     */
    public function __construct(
        SubscriptionManager $subscriptionManager,
        ExportManager $exportManager,
        ImportManager $importManager
    ) {
        $this->subscriptionManager = $subscriptionManager;
        $this->exportManager = $exportManager;
        $this->importManager = $importManager;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/v{version}/payever/products-and-inventory/toggle-subscription",
     *     name="api.action.payever.products_and_inventory.toogle_subscription.legacy",
     *     methods={"POST"}
     * )
     * @return JsonResponse
     * @throws \ReflectionException
     */
    public function toggleSubscriptionLegacy(): JsonResponse
    {
        return $this->toggleSubscription();
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/payever/products-and-inventory/toggle-subscription",
     *     name="api.action.payever.products_and_inventory.toogle_subscription",
     *     methods={"POST"}
     * )
     * @return JsonResponse
     * @throws \ReflectionException
     */
    public function toggleSubscription(): JsonResponse
    {
        $isActive = $this->subscriptionManager->toggleSubscription();
        $errors = $this->subscriptionManager->getErrors();

        return $this->json([
            self::KEY_SUCCESS => !$errors,
            self::KEY_ERRORS => $errors,
            self::KEY_IS_ACTIVE => $isActive,
        ]);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/v{version}/payever/products-and-inventory/export",
     *     name="api.action.payever.products-and-inventory.export.legacy",
     *     methods={"POST"}
     * )
     * @param string $page
     * @return JsonResponse
     */
    public function exportLegacy(): JsonResponse
    {
        return $this->export();
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/payever/products-and-inventory/export",
     *     name="api.action.payever.products-and-inventory.export",
     *     methods={"POST"}
     * )
     * @param string $page
     * @return JsonResponse
     */
    public function export(): JsonResponse
    {
        $result = $this->exportManager->enqueueExport();
        $errors = $this->exportManager->getErrors();

        return $this->json([
            self::KEY_SUCCESS => $result && !$errors,
            self::KEY_ERRORS => $errors,
            self::KEY_BATCH_COUNT => $this->exportManager->getBatchCount(),
        ]);
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route(
     *     "/payever/products-and-inventory/import",
     *     name="frontend.payever.products_and_inventory.import",
     *     defaults={"csrf_protected"=false},
     *     methods={"POST"}
     * )
     * @param Request $request
     * @return JsonResponse
     * @throws \ReflectionException
     */
    public function import(Request $request): JsonResponse
    {
        $result = $this->importManager->import(
            (string) $request->get(SubscriptionManager::PARAM_ACTION),
            (string) $request->get(SubscriptionManager::PARAM_EXTERNAL_ID),
            (string) $request->getContent()
        );
        $errors = $this->importManager->getErrors();
        $result = $result && !$errors;

        return $this->json(
            [
                self::KEY_SUCCESS => $result,
                self::KEY_ERRORS => $errors,
            ],
            $result ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST
        );
    }
}
