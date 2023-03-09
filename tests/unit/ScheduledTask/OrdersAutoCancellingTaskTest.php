<?php

namespace Payever\PayeverPayments\tests\unit\ScheduledTask;

use Payever\PayeverPayments\ScheduledTask\OrdersAutoCancellingTask;

class OrdersAutoCancellingTaskTest extends \PHPUnit\Framework\TestCase
{
    /** @var OrdersAutoCancellingTask */
    private $task;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->task = new OrdersAutoCancellingTask();
    }

    public function testGetTaskName()
    {
        $this->assertNotEmpty($this->task->getTaskName());
    }

    public function testGetDefaultInterval()
    {
        $this->assertNotEmpty($this->task->getDefaultInterval());
    }
}
