<?php

namespace Payever\PayeverPayments\tests\unit\Service;

use Payever\PayeverPayments\Service\PayeverRegistry;
use Shopware\Core\Content\Product\ProductEntity;

class PayeverRegistryTest extends \PHPUnit\Framework\TestCase
{
    public function testGetSet()
    {
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        PayeverRegistry::set(PayeverRegistry::LAST_INWARD_PROCESSED_PRODUCT, $product);
        $this->assertSame($product, PayeverRegistry::get(PayeverRegistry::LAST_INWARD_PROCESSED_PRODUCT));
    }

    public function testGetSetCaseException()
    {
        $this->expectException(\InvalidArgumentException::class);
        PayeverRegistry::set('unexpected_key', true);
    }
}
