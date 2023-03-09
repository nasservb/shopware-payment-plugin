<?php

namespace Payever\PayeverPayments\tests\unit\ScheduledTask;

use Payever\PayeverPayments\ScheduledTask\SynchronizationQueueTask;

class SynchronizationQueueTaskTest extends \PHPUnit\Framework\TestCase
{
    public function testGetTaskName()
    {
        $this->assertNotEmpty(SynchronizationQueueTask::getTaskName());
    }

    public function testGetDefaultInterval()
    {
        $this->assertNotEmpty(SynchronizationQueueTask::getDefaultInterval());
    }
}
