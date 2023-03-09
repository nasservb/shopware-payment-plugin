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

namespace Payever\PayeverPayments\ScheduledTask;

use Payever\PayeverPayments\Service\DataAbstractionLayer\GenericTrait;
use Payever\PayeverPayments\Service\Helper\ConfigHelper;
use Payever\PayeverPayments\Service\Management\GenericManagerTrait;
use Payever\PayeverPayments\Service\Management\SynchronizationManager;
use Payever\PayeverPayments\Service\Management\SynchronizationQueueManager;
use Payever\PayeverPayments\SynchronizationQueue\SynchronizationQueueEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class SynchronizationQueueTaskHandler extends \Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler
{
    use GenericManagerTrait;
    use GenericTrait;

    /**
     * How many times we give queue item a chance to be processed
     */
    private const QUEUE_PROCESSING_MAX_ATTEMPTS = 2;

    /**
     * @var SynchronizationQueueManager
     */
    private $synchronizationQueueManager;

    /**
     * @var SynchronizationManager
     */
    private $synchronizationManager;

    /**
     * @param EntityRepositoryInterface $scheduledTaskRepository
     * @param SynchronizationQueueManager $synchronizationQueueManager
     * @param SynchronizationManager $synchronizationManager
     * @param ConfigHelper $configHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        EntityRepositoryInterface $scheduledTaskRepository,
        SynchronizationQueueManager $synchronizationQueueManager,
        SynchronizationManager $synchronizationManager,
        ConfigHelper $configHelper,
        LoggerInterface $logger
    ) {
        parent::__construct($scheduledTaskRepository);
        $this->synchronizationQueueManager = $synchronizationQueueManager;
        $this->synchronizationManager = $synchronizationManager;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public static function getHandledMessages(): iterable
    {
        return [SynchronizationQueueTask::class];
    }

    /**
     * {@inheritDoc}
     */
    public function run(): void
    {
        if ($this->isProductsSyncEnabled()) {
            $this->execute();
        }
    }

    /**
     * Executes run
     */
    private function execute(): void
    {
        $this->logger->info('START: Processing payever sync action queue');
        $processed = 0;
        $entityCollection = $this->synchronizationQueueManager->getEntities();
        foreach ($entityCollection as $synchronizationQueueEntity) {
            $this->processItem($synchronizationQueueEntity) && ++$processed;
        }
        $this->logger->info('FINISH: Processed queue records', ['processed' => $processed]);
    }

    /**
     * @param SynchronizationQueueEntity $item
     * @return bool
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    private function processItem(SynchronizationQueueEntity $item): bool
    {
        $result = false;
        $attempt = $item->getAttempt();
        try {
            $this->synchronizationManager->handleAction(
                $item->getAction(),
                $item->getDirection(),
                $item->getPayload(),
                true
            );
            $this->synchronizationQueueManager->remove($item);
            $result = true;
        } catch (\Exception $e) {
            $this->logger->warning($e->getMessage(), [$item]);
            if ($attempt >= self::QUEUE_PROCESSING_MAX_ATTEMPTS) {
                $this->logger->info(
                    'Queue item exceeded max processing tries count and going to be removed.' .
                    'This may lead to data loss and out of sync state.'
                );
                $this->synchronizationQueueManager->remove($item);
            } else {
                $item->setAttempt(++$attempt);
                $this->synchronizationQueueManager->updateAttempt($item);
            }
        }

        return $result;
    }
}
