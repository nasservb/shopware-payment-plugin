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

namespace Payever\PayeverPayments\Service\PayeverApi;

use Payever\ExternalIntegration\ThirdParty\Action\ActionHandlerPool;
use Payever\ExternalIntegration\ThirdParty\Action\ActionResult;
use Payever\ExternalIntegration\ThirdParty\Action\BidirectionalActionProcessor;
use Payever\ExternalIntegration\ThirdParty\Action\InwardActionProcessor;
use Payever\ExternalIntegration\ThirdParty\Action\OutwardActionProcessor;
use Psr\Log\LoggerInterface;

/**
 * Class ProcessorFactory
 */
class ProcessorFactory
{
    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ActionHandlerPool
     */
    private $actionHandlerPool;

    /**
     * @var BidirectionalActionProcessor
     */
    private $bidirectionalActionProcessor;

    /**
     * @var InwardActionProcessor
     */
    private $inwardActionProcessor;

    /**
     * @var OutwardActionProcessor
     */
    private $outwardActionProcessor;

    /**
     * @param ClientFactory $clientFactory
     * @param LoggerInterface $logger
     * @param ActionHandlerPool $actionHandlerPool
     */
    public function __construct(
        ClientFactory $clientFactory,
        LoggerInterface $logger,
        ActionHandlerPool $actionHandlerPool
    ) {
        $this->clientFactory = $clientFactory;
        $this->logger = $logger;
        $this->actionHandlerPool = $actionHandlerPool;
    }

    /**
     * @return BidirectionalActionProcessor
     * @throws \Exception
     */
    public function getBidirectionalSyncActionProcessor(): BidirectionalActionProcessor
    {
        if (null === $this->bidirectionalActionProcessor) {
            $this->bidirectionalActionProcessor = new BidirectionalActionProcessor(
                $this->getInwardSyncActionProcessor(),
                $this->getOutwardSyncActionProcessor()
            );
        }

        return $this->bidirectionalActionProcessor;
    }

    /**
     * @return InwardActionProcessor
     */
    public function getInwardSyncActionProcessor(): InwardActionProcessor
    {
        if (null === $this->inwardActionProcessor) {
            $this->inwardActionProcessor = new InwardActionProcessor(
                $this->actionHandlerPool,
                new ActionResult(),
                $this->logger
            );
        }

        return $this->inwardActionProcessor;
    }

    /**
     * @return OutwardActionProcessor
     * @throws \Exception
     */
    public function getOutwardSyncActionProcessor(): OutwardActionProcessor
    {
        if (null === $this->outwardActionProcessor) {
            $this->outwardActionProcessor = new OutwardActionProcessor(
                $this->clientFactory->getProductsApiClient(),
                $this->clientFactory->getInventoryApiClient(),
                $this->logger
            );
        }

        return $this->outwardActionProcessor;
    }

    /**
     * @retrun void
     */
    public function reset(): void
    {
        $this->bidirectionalActionProcessor = $this->inwardActionProcessor = $this->outwardActionProcessor = null;
    }
}
