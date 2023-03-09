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

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Payever\ExternalIntegration\Plugins\Http\ResponseEntity\PluginVersionResponseEntity;
use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Shopware\Core\Framework\Event\BeforeSendResponseEvent;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class PluginController extends AbstractController
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var LoggerInterface|Logger
     */
    private $logger;

    /**
     * @param EventDispatcherInterface $eventDispatcher
     * @param ClientFactory $clientFactory
     * @param SessionInterface $session
     * @param LoggerInterface $logger
     */
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ClientFactory $clientFactory,
        SessionInterface $session,
        LoggerInterface $logger
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->clientFactory = $clientFactory;
        $this->session = $session;
        $this->logger = $logger;
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *    "/api/v{version}/payever/plugin/notifications",
     *     name="api.action.payever.plugin.notifications.legacy",
     *     methods={"GET"}
     * )
     *
     * @return JsonResponse
     */
    public function getNotificationsLegacy(): JsonResponse
    {
        return $this->getNotifications();
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/payever/plugin/notifications",
     *     name="api.action.payever.plugin.notifications",
     *     methods={"GET"}
     * )
     *
     * @return JsonResponse
     */
    public function getNotifications(): JsonResponse
    {
        $messages = [];
        try {
            $pluginsApiClient = $this->clientFactory->getPluginsApiClient();
            $pluginsApiClient->setHttpClientRequestFailureLogLevelOnce(LogLevel::NOTICE);
            /** @var PluginVersionResponseEntity $responseEntity */
            $responseEntity = $pluginsApiClient->getLatestPluginVersion()->getResponseEntity();
            $latestVersion = $responseEntity->getVersion();
            $currentVersion = $pluginsApiClient->getRegistryInfoProvider()->getPluginVersion();
            $notifyVersionKey = sprintf('payever.update.notified%s', $latestVersion);

            if (
                version_compare($currentVersion, $latestVersion, '<')
                && !$this->session->has($notifyVersionKey)
            ) {
                $messages[] = [
                    'title' => 'Plugin updates',
                    'message' => sprintf(
                        'Payever plugin version %s released. Please, update the plugin from Plugins > Updates tab.',
                        $latestVersion
                    ),
                ];
                $this->session->set($notifyVersionKey, true);
            }
        } catch (\Exception $e) {
            $this->logger->notice(sprintf('Plugin version checking failed: %s', $e->getMessage()));
        }

        return $this->json($messages);
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/v{version}/payever/download-payever-log",
     *     name="payever.administration.download_log.legacy",
     *     methods={"GET"}
     * )
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
     */
    public function downloadLogLegacy()
    {
        return $this->downloadLog();
    }

    /**
     * @RouteScope(scopes={"api"})
     * @Route(
     *     "/api/_action/payever/download-payever-log",
     *     name="payever.administration.download_log",
     *     methods={"GET"}
     * )
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|JsonResponse
     */
    public function downloadLog()
    {
        $handlers = $this->logger->getHandlers();
        foreach ($handlers as $handler) {
            if ($handler instanceof RotatingFileHandler) {
                $binaryFileResponse = $this->file($handler->getUrl());
                $binaryFileResponse->setCache(['private' => true]);
                $listeners = $this->eventDispatcher->getListeners(BeforeSendResponseEvent::class);
                foreach ($listeners as $listener) {
                    if (is_callable($listener)) {
                        $this->eventDispatcher->removeListener(BeforeSendResponseEvent::class, $listener);
                    }
                }

                return $binaryFileResponse;
            }
        }

        return $this->json(['error' => 'Unable to load log']);
    }
}
