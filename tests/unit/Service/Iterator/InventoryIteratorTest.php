<?php

namespace Payever\PayeverPayments\tests\unit\Service\Iterator;

use Payever\PayeverPayments\Service\Iterator\InventoryIterator;
use Shopware\Core\Content\Product\ProductEntity;

class InventoryIteratorTest extends \PHPUnit\Framework\TestCase
{
    public function testCurrent()
    {
        $item = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $iterator = new InventoryIterator([$item]);
        $this->assertNotEmpty($iterator->current());
    }
}
