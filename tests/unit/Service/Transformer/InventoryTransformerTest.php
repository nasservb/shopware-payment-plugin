<?php

namespace Payever\PayeverPayments\tests\unit\Service\Transformer;

use Payever\PayeverPayments\Service\Helper\ConfigHelper;
use Payever\PayeverPayments\Service\Transformer\InventoryTransformer;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class InventoryTransformerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var MockObject|ConfigHelper
     */
    private $configHelper;

    /**
     * @var InventoryTransformer
     */
    private $transformer;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->productRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configHelper = $this->getMockBuilder(ConfigHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->transformer = new InventoryTransformer(
            $this->productRepository,
            $this->configHelper
        );
    }

    public function testUpdateStock()
    {
        $this->productRepository->expects($this->once())
            ->method('update');
        $this->transformer->updateStock('some-product-id', 1);
    }

    public function testTransformFromShopwareToPayever()
    {
        $this->assertNotEmpty(
            $this->transformer->transformFromShopwareToPayever(
                $this->getMockBuilder(ProductEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock(),
                1
            )
        );
    }

    public function testTransformFromCreatedShopwareToPayever()
    {
        $this->assertNotEmpty(
            $this->transformer->transformFromCreatedShopwareToPayever(
                $this->getMockBuilder(ProductEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            )
        );
    }
}
