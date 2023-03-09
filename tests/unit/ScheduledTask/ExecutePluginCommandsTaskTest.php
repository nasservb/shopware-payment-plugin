<?php

namespace Payever\PayeverPayments\tests\unit\ScheduledTask;

use Payever\PayeverPayments\ScheduledTask\ExecutePluginCommandsTask;

class ExecutePluginCommandsTaskTest extends \PHPUnit\Framework\TestCase
{
    /** @var ExecutePluginCommandsTask */
    private $task;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->task = new ExecutePluginCommandsTask();
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
