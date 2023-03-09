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

use Payever\PayeverPayments\ScheduledTask\ExecutePluginCommandsTaskHandler;
use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use Payever\PayeverPayments\Service\Setting\SettingsServiceInterface;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Store\Exception\StoreSignatureValidationException;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\UriSigner;
use Symfony\Component\Routing\Annotation\Route;

class PluginCommandController extends StorefrontController
{
    /** @var ExecutePluginCommandsTaskHandler */
    private $taskHandler;

    /** @var UriSigner */
    private $uriSigner;

    /** @var SettingsServiceInterface */
    private $settingsService;

    /** @var ClientFactory */
    private $apiClientFactory;

    /**
     * @param ExecutePluginCommandsTaskHandler $taskHandler
     * @param UriSigner $uriSigner
     */
    public function __construct(
        SettingsServiceInterface $settingsService,
        ClientFactory $apiClientFactory,
        ExecutePluginCommandsTaskHandler $taskHandler,
        UriSigner $uriSigner
    ) {
        $this->settingsService = $settingsService;
        $this->apiClientFactory = $apiClientFactory;
        $this->taskHandler = $taskHandler;
        $this->uriSigner = $uriSigner;
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/payever/plugin/command", name="payever.plugin.command", methods={"POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws StoreSignatureValidationException
     */
    public function execute(Request $request): JsonResponse
    {
        if (!$this->uriSigner->check($request->getRequestUri())) {
            throw new StoreSignatureValidationException('Signature not valid');
        }
        $this->taskHandler->run();

        return $this->json(['ok']);
    }

    /**
     * @RouteScope(scopes={"storefront"})
     * @Route("/payever/plugin/execute_commands", name="payever.plugin.execute_commands", methods={"GET","POST"})
     *
     * @param Request $request
     *
     * @return JsonResponse
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    public function executeCommands(Request $request): Response
    {
        $result = [];
        try {
            $token = (string) $request->query->get('token', '');
            $businessUuid = $this->settingsService->getSettings()->getBusinessUuid();
            $isValidToken = $this->apiClientFactory
                ->getThirdPartyPluginsApiClient()
                ->validateToken($businessUuid, $token);
            if ($isValidToken) {
                $this->taskHandler->run();
                $result[] = [
                    'message' => 'The commands have been executed successfully',
                ];

                return $this->json($result);
            } else {
                throw new \Exception('Invalid token');
            }
        } catch (\Exception $e) {
            $result[] = [
                'message' => 'The commands haven\'t been executed successfully: ' . $e->getMessage(),
            ];
        }

        return $this->json($result);
    }
}
