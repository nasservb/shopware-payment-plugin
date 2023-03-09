<?php

namespace Payever\PayeverPayments\tests\unit\Messenger;

use Payever\PayeverPayments\Messenger\ExportProducer;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ExportProducerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|MessageBusInterface
     */
    private $bus;

    /**
     * @var ExportProducer
     */
    private $producer;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->bus = $this->getMockBuilder(MessageBusInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->producer = new ExportProducer($this->bus);
    }

    public function testProduce()
    {
        $this->bus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));
        $this->producer->produce(5, 0);
    }
}
