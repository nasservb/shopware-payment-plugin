<?php

namespace Payever\PayeverPayments\tests\unit\Messenger;

use Payever\PayeverPayments\Messenger\ExportBatchMessage;

class ExportBatchMessageTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ExportBatchMessage
     */
    private $message;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->message = new ExportBatchMessage();
    }

    public function testSetGetLimit()
    {
        $this->message->setLimit($limit = 5);
        $this->assertEquals($limit, $this->message->getLimit());
    }

    public function testSetGetOffset()
    {
        $this->message->setOffset($offset = 10);
        $this->assertEquals($offset, $this->message->getOffset());
    }
}
