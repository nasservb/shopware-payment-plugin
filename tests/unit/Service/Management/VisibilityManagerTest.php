<?php

namespace Payever\PayeverPayments\tests\unit\Service\Management;

use Payever\PayeverPayments\Service\Management\VisibilityManager;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class VisibilityManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $productVisibilityRepository;

    /**
     * @var MockObject|VisibilityManager
     */
    private $manager;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->salesChannelRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->productVisibilityRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->manager = new VisibilityManager(
            $this->salesChannelRepository,
            $this->productVisibilityRepository
        );
    }

    public function testGetPreparedVisibilityCollection()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getActive')
            ->willReturn(true);
        $product->expects($this->once())
            ->method('getCreatedAt')
            ->willReturn(new \DateTime());
        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn($entityCollection = new EntityCollection());
        /** @var MockObject|SalesChannelEntity $channelEntity */
        $channelEntity = $this->getMockBuilder(SalesChannelEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $channelEntity->expects($this->once())
            ->method('getUniqueIdentifier')
            ->willReturn($id = 'some-id');
        $entityCollection->add($channelEntity);
        $channelEntity->expects($this->once())
            ->method('getId')
            ->willReturn($id);
        $this->assertNotEmpty($this->manager->getPreparedVisibilityCollection($product));
    }

    public function testGetPreparedVisibilityCollectionCaseFound()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getVisibilities')
            ->willReturn($collection = new ProductVisibilityCollection());
        /** @var MockObject|ProductVisibilityEntity $productVisibilityEntity */
        $productVisibilityEntity = $this->getMockBuilder(ProductVisibilityEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $productVisibilityEntity->expects($this->once())
            ->method('getUniqueIdentifier')
            ->willReturn('some-id');
        $collection->add($productVisibilityEntity);
        $product->expects($this->once())
            ->method('getActive')
            ->willReturn(true);
        $this->assertNotEmpty($this->manager->getPreparedVisibilityCollection($product));
    }

    public function testGetVisibilityData()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getVisibilities')
            ->willReturn($collection = new ProductVisibilityCollection());
        /** @var MockObject|ProductVisibilityEntity $productVisibilityEntity */
        $productVisibilityEntity = $this->getMockBuilder(ProductVisibilityEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $productVisibilityEntity->expects($this->once())
            ->method('getUniqueIdentifier')
            ->willReturn($id = 'some-id');
        $collection->add($productVisibilityEntity);
        $productVisibilityEntity->expects($this->once())
            ->method('getId')
            ->willReturn($id);
        $productVisibilityEntity->expects($this->once())
            ->method('getProductId')
            ->willReturn('some-product-id');
        $productVisibilityEntity->expects($this->once())
            ->method('getSalesChannelId')
            ->willReturn('some-channel-id');
        $productVisibilityEntity->expects($this->once())
            ->method('getVisibility')
            ->willReturn(ProductVisibilityDefinition::VISIBILITY_ALL);
        $this->assertNotEmpty($this->manager->getVisibilityData($product));
    }
}
