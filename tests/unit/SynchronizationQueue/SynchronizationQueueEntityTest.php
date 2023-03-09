<?php

namespace Payever\PayeverPayments\tests\unit\SynchronizationQueue;

use Payever\PayeverPayments\SynchronizationQueue\SynchronizationQueueEntity;

class SynchronizationQueueEntityTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var SynchronizationQueueEntity
     */
    private $entity;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->entity = new SynchronizationQueueEntity();
    }

    public function testSetGetAction()
    {
        $this->entity->setAction($action = 'some-action');
        $this->assertEquals($action, $this->entity->getAction());
    }

    public function testSetGetDirection()
    {
        $this->entity->setDirection($direction = 'some-direction');
        $this->assertEquals($direction, $this->entity->getDirection());
    }

    public function testSetGetPayload()
    {
        $this->entity->setPayload($payload = \json_encode(['some' => 'data']));
        $this->assertEquals($payload, $this->entity->getPayload());
    }

    public function testSetGetAttempt()
    {
        $this->entity->setAttempt($attempt = 2);
        $this->assertEquals($attempt, $this->entity->getAttempt());
    }
}
