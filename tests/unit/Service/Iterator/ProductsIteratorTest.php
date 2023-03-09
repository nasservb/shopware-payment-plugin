<?php

namespace Payever\PayeverPayments\tests\unit\Service\Iterator;

use Payever\PayeverPayments\Service\Iterator\ProductsIterator;
use Payever\PayeverPayments\Service\Transformer\ProductTransformer;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Content\Product\ProductEntity;

class ProductsIteratorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|ProductTransformer
     */
    private $productTransformer;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->productTransformer = $this->getMockBuilder(ProductTransformer::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testCurrent()
    {
        $item = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $iterator = new ProductsIterator($this->productTransformer, [$item]);
        $this->assertNotEmpty($iterator->current());
    }
}
