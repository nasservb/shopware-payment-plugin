<?php

namespace Payever\PayeverPayments\tests\unit\Service\Management;

use Payever\PayeverPayments\Service\Helper\ConfigHelper;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class GenericManagerTraitTest extends \PHPUnit\Framework\TestCase
{
    use \Payever\PayeverPayments\Service\Management\GenericManagerTrait;

    /**
     * @var MockObject|ConfigHelper
     */
    private $configHelper;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->configHelper = $this->getMockBuilder(ConfigHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testGetErrors()
    {
        $this->assertEmpty($this->getErrors());
    }

    public function testIsOutwardSyncEnabled()
    {
        $this->configHelper->expects($this->once())
            ->method('isProductsOutwardSyncEnabled')
            ->willReturn(false);
        $this->assertFalse($this->isProductsOutwardSyncEnabled());
        $this->assertNotEmpty($this->getErrors());
    }
}
