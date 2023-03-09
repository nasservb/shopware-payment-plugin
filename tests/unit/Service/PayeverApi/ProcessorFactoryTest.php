<?php

namespace Payever\PayeverPayments\tests\unit\Service\PayeverApi;

use Payever\ExternalIntegration\ThirdParty\Action\ActionHandlerPool;
use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use Payever\PayeverPayments\Service\PayeverApi\ProcessorFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class ProcessorFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|ClientFactory
     */
    private $clientFactory;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var MockObject|ActionHandlerPool
     */
    private $actionHandlerPool;

    /**
     * @var ProcessorFactory
     */
    private $factory;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->clientFactory = $this->getMockBuilder(ClientFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->actionHandlerPool = $this->getMockBuilder(ActionHandlerPool::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->factory = new ProcessorFactory(
            $this->clientFactory,
            $this->logger,
            $this->actionHandlerPool
        );
    }

    public function testGetBidirectionalSyncActionProcessor()
    {
        $this->assertNotEmpty($this->factory->getBidirectionalSyncActionProcessor());
    }

    public function testGetInwardSyncActionProcessor()
    {
        $this->assertNotEmpty($this->factory->getInwardSyncActionProcessor());
    }

    public function testGetInwardSyncActionProcessorCaseReset()
    {
        $this->assertNotEmpty($this->factory->getInwardSyncActionProcessor());
        $this->factory->reset();
        $this->assertNotEmpty($this->factory->getInwardSyncActionProcessor());
    }

    public function testGetOutwardSyncActionProcessor()
    {
        $this->assertNotEmpty($this->factory->getOutwardSyncActionProcessor());
    }
}
