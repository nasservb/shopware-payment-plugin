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

use Payever\ExternalIntegration\ThirdParty\Enum\DirectionEnum;
use Payever\PayeverPayments\Service\Helper\ConfigHelper;
use Psr\Log\LoggerInterface;

class ImportManager
{
    use GenericManagerTrait;

    /**
     * @var SubscriptionManager
     */
    private $subscriptionManager;

    /**
     * @var SynchronizationManager
     */
    private $synchronizationManager;

    /**
     * @param SubscriptionManager $subscriptionManager
     * @param SynchronizationManager $synchronizationManager
     * @param ConfigHelper $configHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        SubscriptionManager $subscriptionManager,
        SynchronizationManager $synchronizationManager,
        ConfigHelper $configHelper,
        LoggerInterface $logger
    ) {
        $this->subscriptionManager = $subscriptionManager;
        $this->synchronizationManager = $synchronizationManager;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
    }

    /**
     * @param string $syncAction
     * @param string $externalId
     * @param string $payload
     * @return bool
     * @throws \ReflectionException
     */
    public function import(string $syncAction, string $externalId, string $payload): bool
    {
        $this->cleanMessages();
        if (
            $this->isProductsSyncEnabled() && $this->isValidAction($syncAction)
            && $this->isValidExternalId($externalId) && $this->isValidPayload($payload)
        ) {
            $this->synchronizationManager->handleAction(
                $syncAction,
                DirectionEnum::INWARD,
                $payload
            );
        }
        $this->logMessages();

        return !$this->errors;
    }

    /**
     * @param string $action
     * @return bool
     * @throws \ReflectionException
     */
    private function isValidAction(string $action): bool
    {
        $result = true;
        if (!in_array($action, $this->subscriptionManager->getSupportedActions(), true)) {
            $this->errors[] = 'The action is not supported';
            $this->debugMessages[] = [
                'message' => sprintf('Attempt to call action "%s"', $action),
            ];
            $result = false;
        }

        return $result;
    }

    /**
     * @param string $externalId
     * @return bool
     */
    private function isValidExternalId(string $externalId): bool
    {
        $expectedExternalId = $this->configHelper->getProductsSyncExternalId();
        $result = $expectedExternalId === $externalId;
        if (!$result) {
            $this->errors[] = 'ExternalId is invalid';
            $this->debugMessages[] = [
                'message' => sprintf(
                    'Expected external id is "%s", actual is "%s"',
                    $expectedExternalId,
                    $externalId
                ),
            ];
        }

        return $result;
    }

    /**
     * @param string $payload
     * @return bool
     */
    private function isValidPayload($payload)
    {
        $result = \json_decode($payload, true) !== null;
        $this->debugMessages[] = [
            'message' => 'Synchronization payload',
            'context' => [$payload],
        ];
        if (!$result) {
            $this->errors[] = 'Cannot decode payload';
        }

        return $result;
    }
}
