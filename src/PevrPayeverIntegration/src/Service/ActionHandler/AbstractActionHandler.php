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

namespace Payever\PayeverPayments\Service\ActionHandler;

use Payever\ExternalIntegration\Core\Base\MessageEntity;
use Payever\ExternalIntegration\Core\Http\RequestEntity;
use Payever\ExternalIntegration\ThirdParty\Action\ActionHandlerInterface;
use Payever\ExternalIntegration\ThirdParty\Action\ActionPayload;
use Payever\ExternalIntegration\ThirdParty\Action\ActionResult;
use Payever\PayeverPayments\Service\PayeverRegistry;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;

abstract class AbstractActionHandler implements ActionHandlerInterface, LoggerAwareInterface
{
    use \Payever\PayeverPayments\Service\DataAbstractionLayer\GenericTrait;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var ActionResult|null
     */
    protected $actionResult;

    /**
     * @param LoggerInterface $logger
     * @return void
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function handle(ActionPayload $actionPayload, ActionResult $actionResult): void
    {
        $this->actionResult = $actionResult;
        /** @var RequestEntity $requestEntity */
        $requestEntity = $actionPayload->getPayloadEntity();
        if (!$this->validate($requestEntity)) {
            return;
        }
        try {
            $this->process($requestEntity);
            $this->incrementActionResult();
        } catch (\Exception $e) {
            $this->actionResult->incrementSkipped();
            $this->actionResult->addException($e);
            $this->logger->warning($e->getMessage());
        }
    }

    /**
     * @param MessageEntity $entity
     * @return bool
     */
    protected function validate(MessageEntity $entity): bool
    {
        $result = true;
        if (!$entity->getSku()) {
            $this->actionResult->incrementSkipped();
            $this->actionResult->addError(
                sprintf(
                    'Entity has empty SKU: "%s"',
                    $entity->toString()
                )
            );
            $result = false;
        }

        return $result;
    }

    /**
     * @param ProductEntity $product
     */
    protected function pushToRegistry(ProductEntity $product): void
    {
        PayeverRegistry::set(PayeverRegistry::LAST_INWARD_PROCESSED_PRODUCT, $product);
    }

    /**
     * @param MessageEntity $entity
     */
    abstract protected function process($entity): void;

    /**
     * Increment action result count: created, updated etc.
     */
    abstract protected function incrementActionResult(): void;
}
