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

namespace Payever\PayeverPayments\Service\Management;

use Payever\PayeverPayments\SynchronizationQueue\SynchronizationQueueEntity;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

class SynchronizationQueueManager
{
    use \Payever\PayeverPayments\Service\DataAbstractionLayer\GenericTrait;

    /**
     * How many queue items we process during one job run
     */
    private const QUEUE_PROCESSING_SIZE = 25;

    /**
     * @var EntityRepositoryInterface
     */
    private $synchronizationQueueRepository;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param EntityRepositoryInterface $synchronizationQueueRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        EntityRepositoryInterface $synchronizationQueueRepository,
        LoggerInterface $logger
    ) {
        $this->synchronizationQueueRepository = $synchronizationQueueRepository;
        $this->logger = $logger;
    }

    /**
     * @param string $action
     * @param string $direction
     * @param string $payload
     */
    public function enqueueAction(string $action, string $direction, string $payload): void
    {
        try {
            $this->synchronizationQueueRepository->upsert(
                [
                    [
                        SynchronizationQueueEntity::FIELD_ID => $this->getRandomHex(),
                        SynchronizationQueueEntity::FIELD_ACTION => $action,
                        SynchronizationQueueEntity::FIELD_DIRECTION => $direction,
                        SynchronizationQueueEntity::FIELD_PAYLOAD => $payload,
                    ]
                ],
                $this->getContext()
            );
        } catch (\Exception $exception) {
            $this->logger->warning(
                $exception->getMessage(),
                [$action, $direction, $payload]
            );
        }
    }

    /**
     * @param int $limit
     * @return SynchronizationQueueEntity[]
     */
    public function getEntities(int $limit = self::QUEUE_PROCESSING_SIZE): array
    {
        $criteria = new Criteria();
        if ($limit) {
            $criteria->setLimit($limit);
        }
        $criteria->addSorting(new FieldSorting('createdAt'));
        /** @var SynchronizationQueueEntity[] $entities */
        $entities = $this->synchronizationQueueRepository->search($criteria, $this->getContext())
            ->getEntities()
            ->getElements();

        return $entities;
    }

    /**
     * @param SynchronizationQueueEntity $entity
     */
    public function updateAttempt(SynchronizationQueueEntity $entity)
    {
        $this->synchronizationQueueRepository->update(
            [
                [
                    SynchronizationQueueEntity::FIELD_ID => $entity->getId(),
                    SynchronizationQueueEntity::FIELD_ATTEMPT => $entity->getAttempt(),
                ]
            ],
            $this->getContext()
        );
    }

    /**
     * @param SynchronizationQueueEntity $entity
     */
    public function remove(SynchronizationQueueEntity $entity)
    {
        $this->synchronizationQueueRepository->delete(
            [
                [
                    SynchronizationQueueEntity::FIELD_ID => $entity->getId(),
                ]
            ],
            $this->getContext()
        );
    }

    /**
     * @return void
     */
    public function emptyQueue()
    {
        $entities = $this->getEntities(0);
        foreach ($entities as $entity) {
            $this->remove($entity);
        }
    }
}
