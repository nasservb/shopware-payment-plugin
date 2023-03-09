<?php

namespace Payever\PayeverPayments\tests\unit\Service\Management;

use Payever\PayeverPayments\Service\Management\ManufacturerManager;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class ManufacturerManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $manufacturerRepository;

    /**
     * @var ManufacturerManager
     */
    private $manager;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->manufacturerRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->manager = new ManufacturerManager($this->manufacturerRepository);
    }

    public function testGetPreparedManufacturer()
    {
        $this->manufacturerRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn(
                $this->getMockBuilder(EntityCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->assertNotEmpty(
            $this->manager->getPreparedManufacturer(
                $this->getMockBuilder(ProductEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            )
        );
    }

    public function testGetManufacturerData()
    {
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getManufacturer')
            ->willReturn(
                $manufacturer = $this->getMockBuilder(ProductManufacturerEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $manufacturer->expects($this->once())
            ->method('getId')
            ->willReturn('some-id');
        $this->assertNotEmpty($this->manager->getManufacturerData($product));
    }
}
