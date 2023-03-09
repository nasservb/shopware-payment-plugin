<?php

namespace Payever\PayeverPayments\tests\unit\SynchronizationQueue;

use Payever\PayeverPayments\SynchronizationQueue\SynchronizationQueueDefinition;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;

class SynchronizationQueueDefinitionTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|DefinitionInstanceRegistry
     */
    private $registry;

    /**
     * @var SynchronizationQueueDefinition
     */
    private $definition;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->registry = $this->getMockBuilder(DefinitionInstanceRegistry::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->definition = new SynchronizationQueueDefinition();
        $this->definition->compile($this->registry);
    }

    public function testGetEntityName()
    {
        $this->assertNotEmpty($this->definition->getEntityName());
    }

    public function testGetEntityClass()
    {
        $this->assertNotEmpty($this->definition->getEntityClass());
    }

    public function testGetCollectionClass()
    {
        $this->assertNotEmpty($this->definition->getCollectionClass());
    }

    public function testGetFields()
    {
        $this->assertNotEmpty($this->definition->getFields());
    }
}
