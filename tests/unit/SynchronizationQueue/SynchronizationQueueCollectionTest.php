<?php

namespace Payever\PayeverPayments\tests\unit\SynchronizationQueue;

use Payever\PayeverPayments\SynchronizationQueue\SynchronizationQueueCollection;
use Payever\PayeverPayments\SynchronizationQueue\SynchronizationQueueEntity;
use PHPUnit\Framework\MockObject\MockObject;

class SynchronizationQueueCollectionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var SynchronizationQueueCollection
     */
    private $collection;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->collection = new SynchronizationQueueCollection();
    }

    public function testAdd()
    {
        /** @var MockObject|SynchronizationQueueEntity $entity */
        $entity = $this->getMockBuilder(SynchronizationQueueEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $entity->expects($this->once())
            ->method('getUniqueIdentifier')
            ->willReturn('some-id');
        $this->collection->add($entity);
    }
}
