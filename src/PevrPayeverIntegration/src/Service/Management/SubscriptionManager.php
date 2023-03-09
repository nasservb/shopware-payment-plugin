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

namespace Payever\PayeverPayments\Service\Management;

use Payever\ExternalIntegration\Core\Enum\ChannelSet;
use Payever\ExternalIntegration\Core\PseudoRandomStringGenerator;
use Payever\ExternalIntegration\ThirdParty\Enum\ActionEnum;
use Payever\ExternalIntegration\ThirdParty\Http\MessageEntity\SubscriptionActionEntity;
use Payever\ExternalIntegration\ThirdParty\Http\RequestEntity\SubscriptionRequestEntity;
use Payever\ExternalIntegration\ThirdParty\Http\ResponseEntity\SubscriptionResponseEntity;
use Payever\ExternalIntegration\ThirdParty\ThirdPartyApiClient;
use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use Payever\PayeverPayments\Service\Setting\SettingsService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;

class SubscriptionManager
{
    use GenericManagerTrait;

    public const PARAM_ACTION = 'sync_action';
    public const PARAM_EXTERNAL_ID = 'external_id';

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var PseudoRandomStringGenerator
     */
    private $pseudoRandomStringGenerator;

    /**
     * @var SettingsService
     */
    private $settingsService;

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var SynchronizationQueueManager
     */
    private $synchronizationQueueManager;

    /**
     * @var ThirdPartyApiClient
     */
    private $thirdPartyApiClient;

    /**
     * @param RouterInterface $router
     * @param PseudoRandomStringGenerator $pseudoRandomStringGenerator
     * @param SettingsService $settingsService
     * @param ClientFactory $clientFactory
     * @param SynchronizationQueueManager $synchronizationQueueManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        RouterInterface $router,
        PseudoRandomStringGenerator $pseudoRandomStringGenerator,
        SettingsService $settingsService,
        ClientFactory $clientFactory,
        SynchronizationQueueManager $synchronizationQueueManager,
        LoggerInterface $logger
    ) {
        $this->router = $router;
        $this->pseudoRandomStringGenerator = $pseudoRandomStringGenerator;
        $this->settingsService = $settingsService;
        $this->clientFactory = $clientFactory;
        $this->synchronizationQueueManager = $synchronizationQueueManager;
        $this->logger = $logger;
    }

    /**
     * @return bool
     */
    public function toggleSubscription(): bool
    {
        $this->cleanMessages();
        $configCarrier = $this->settingsService->getSettings();
        $isEnabled = $configCarrier->isProductsSyncEnabled();
        $result = !$isEnabled;
        try {
            $isEnabled ? $this->disable() : $this->enable();
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
            $result = false;
        }
        $this->logMessages();

        return $result;
    }

    /**
     * @return void
     */
    public function disable()
    {
        try {
            $this->getThirdPartyApiClient()->unsubscribe($this->getSubscriptionEntity());
        } catch (\Exception $e) {
            $message = 'Unable to unsubscribe';
            $this->logger->warning($message);
        } finally {
            $this->cleanup();
        }
    }

    /**
     * @return array
     * @throws \ReflectionException
     */
    public function getSupportedActions(): array
    {
        return array_diff(
            ActionEnum::enum(),
            [
                ActionEnum::ACTION_PRODUCTS_SYNC,
            ]
        );
    }

    /**
     * @throws \Exception
     * @throws \ReflectionException
     */
    private function enable()
    {
        $subscriptionEntity = $this->getSubscriptionEntity();
        foreach ($this->getSupportedActions() as $actionName) {
            $actionUrl = $this->router->generate(
                'frontend.payever.products_and_inventory.import',
                [
                    self::PARAM_ACTION => $actionName,
                    self::PARAM_EXTERNAL_ID => $subscriptionEntity->getExternalId(),
                ],
                RouterInterface::ABSOLUTE_URL
            );
            $subscriptionEntity->addAction(
                new SubscriptionActionEntity(
                    [
                        'name' => $actionName,
                        'url' => $actionUrl,
                        'method' => 'POST',
                    ]
                )
            );
        }
        $this->getThirdPartyApiClient()->subscribe($subscriptionEntity);
        $response = $this->getThirdPartyApiClient()->getSubscriptionStatus($subscriptionEntity);
        /** @var SubscriptionResponseEntity $subscriptionResponseEntity */
        $subscriptionResponseEntity = $response->getResponseEntity();
        $this->settingsService->updateSettings([
            'isProductsSyncEnabled' => (bool) $subscriptionResponseEntity,
            'productsSyncExternalId' => $subscriptionEntity->getExternalId(),
        ]);
    }

    /**
     * @return SubscriptionRequestEntity
     * @throws \Exception
     */
    private function getSubscriptionEntity(): SubscriptionRequestEntity
    {
        $configCarrier = $this->settingsService->getSettings();
        $externalId = $configCarrier->getProductsSyncExternalId();
        if (!$externalId) {
            $externalId = $this->pseudoRandomStringGenerator->generate();
        }
        $subscriptionEntity = new SubscriptionRequestEntity();
        $subscriptionEntity->setExternalId($externalId);
        $subscriptionEntity->setBusinessUuid($configCarrier->getBusinessUuid());
        $subscriptionEntity->setThirdPartyName(ChannelSet::CHANNEL_SHOPWARE);

        return $subscriptionEntity;
    }

    /**
     * @return void
     */
    protected function cleanup()
    {
        try {
            $this->synchronizationQueueManager->emptyQueue();
            $this->settingsService->updateSettings([
                'isProductsSyncEnabled' => false,
                'productsSyncExternalId' => null,
            ]);
            $this->settingsService->getSettings()->setIsProductsSyncEnabled(false);
            $this->settingsService->getSettings()->setProductsSyncExternalId(null);
        } catch (\Exception $exception) {
            $this->logger->warning($exception->getMessage());
        }
    }

    /**
     * @return ThirdPartyApiClient
     * @throws \Exception
     */
    private function getThirdPartyApiClient(): ThirdPartyApiClient
    {
        return null === $this->thirdPartyApiClient
            ? $this->thirdPartyApiClient = $this->clientFactory->getThirdPartyApiClient()
            : $this->thirdPartyApiClient;
    }
}
