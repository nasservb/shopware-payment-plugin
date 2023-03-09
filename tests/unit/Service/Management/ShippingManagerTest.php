<?php

namespace Payever\PayeverPayments\tests\unit\Service\Management;

use Payever\ExternalIntegration\Products\Http\MessageEntity\ProductShippingEntity;
use Payever\ExternalIntegration\Products\Http\RequestEntity\ProductRequestEntity;
use Payever\PayeverPayments\Service\Management\ShippingManager;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Content\Product\ProductEntity;

class ShippingManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ShippingManager
     */
    private $manager;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->manager = new ShippingManager();
    }

    public function testGetShipping()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getWidth')
            ->willReturn(10.5);
        $product->expects($this->once())
            ->method('getLength')
            ->willReturn(20.5);
        $product->expects($this->once())
            ->method('getHeight')
            ->willReturn(30.5);
        $product->expects($this->once())
            ->method('getWeight')
            ->willReturn(0.5);
        $this->assertEquals(
            [
                'measure_size' => 'cm',
                'measure_mass' => 'kg',
                'width' => 1.05,
                'length' => 2.05,
                'height' => 3.05,
                'weight' => 0.5,
            ],
            $this->manager->getShipping($product)
        );
    }

    public function testSetShipping()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|ProductRequestEntity $requestEntity */
        $requestEntity = $this->getMockBuilder(ProductRequestEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getShipping'])
            ->getMock();
        $requestEntity->expects($this->once())
            ->method('getShipping')
            ->willReturn(
                $shipping = $this->getMockBuilder(ProductShippingEntity::class)
                    ->disableOriginalConstructor()
                    ->addMethods([
                        'getMeasureSize',
                        'getMeasureMass',
                        'getWidth',
                        'getLength',
                        'getHeight',
                        'getWeight',
                    ])
                    ->getMock()
            );
        $shipping->expects($this->once())
            ->method('getMeasureSize')
            ->willReturn(ShippingManager::SIZE_CENTIMETER);
        $shipping->expects($this->once())
            ->method('getMeasureMass')
            ->willReturn(ShippingManager::MASS_KILOGRAM);
        $shipping->expects($this->once())
            ->method('getWidth')
            ->willReturn(1.5);
        $shipping->expects($this->once())
            ->method('getLength')
            ->willReturn(2.5);
        $shipping->expects($this->once())
            ->method('getHeight')
            ->willReturn(3.5);
        $shipping->expects($this->once())
            ->method('getWeight')
            ->willReturn(1.3);
        $product->expects($this->once())
            ->method('setWidth')
            ->with(15.0);
        $product->expects($this->once())
            ->method('setLength')
            ->with(25.0);
        $product->expects($this->once())
            ->method('setHeight')
            ->with(35.0);
        $product->expects($this->once())
            ->method('setWeight')
            ->with(1.3);
        $this->manager->setShipping($product, $requestEntity);
    }

    public function testGetShippingData()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getWidth')
            ->willReturn(11.0);
        $product->expects($this->once())
            ->method('getLength')
            ->willReturn(12.0);
        $product->expects($this->once())
            ->method('getHeight')
            ->willReturn(13.0);
        $product->expects($this->once())
            ->method('getWeight')
            ->willReturn(0.1);

        $this->assertEquals(
            [
                'width' => 11.0,
                'length' => 12.0,
                'height' => 13.0,
                'weight' => 0.1,
            ],
            $this->manager->getShippingData($product)
        );
    }
}
