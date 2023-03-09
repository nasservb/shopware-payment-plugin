<?php

namespace Payever\PayeverPayments\tests\unit\Service\Management;

use Payever\ExternalIntegration\Products\Http\RequestEntity\ProductRequestEntity;
use Payever\PayeverPayments\Service\Management\GalleryManager;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaRepositoryDecorator;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class GalleryManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|MediaService
     */
    private $mediaService;

    /**
     * @var MockObject|MediaRepositoryDecorator
     */
    private $mediaRepositoryDecorator;

    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $productMediaRepository;

    /**
     * @var GalleryManager
     */
    private $manager;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->mediaService = $this->getMockBuilder(MediaService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->mediaRepositoryDecorator = $this->getMockBuilder(MediaRepositoryDecorator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->productMediaRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->manager = new GalleryManager(
            $this->mediaService,
            $this->mediaRepositoryDecorator,
            $this->productMediaRepository
        );
    }

    public function testGetImages()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getMedia')
            ->willReturn(
                $productMediaCollection = $this->getMockBuilder(ProductMediaCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $productMediaCollection->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $productMediaEntity = $this->getMockBuilder(ProductMediaEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $productMediaEntity->expects($this->once())
            ->method('getMedia')
            ->willReturn(
                $mediaEntity = $this->getMockBuilder(MediaEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $mediaEntity->expects($this->once())
            ->method('getMediaType')
            ->willReturn(
                $this->getMockBuilder(ImageType::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $mediaEntity->expects($this->once())
            ->method('getUrl')
            ->willReturn('http://some.domain/path.jpg');
        $this->assertNotEmpty($this->manager->getImages($product));
    }

    public function testGetPreparedMedia()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|ProductRequestEntity $requestEntity */
        $requestEntity = $this->getMockBuilder(ProductRequestEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getImages', 'getImagesUrl'])
            ->getMock();
        $requestEntity->expects($this->once())
            ->method('getImages')
            ->willReturn([
                $imageName1 = 'image-1.jpg',
                $imageName2 = 'image-2.jpg',
                $imageName3 = 'image-3.jpg',
            ]);
        $requestEntity->expects($this->once())
            ->method('getImagesUrl')
            ->willReturn([
                sprintf('http://some.domain/%s', $imageName1),
                sprintf('http://some.domain/%s', $imageName2),
                sprintf('http://some.domain/%s', $imageName3),
            ]);
        $this->mediaRepositoryDecorator->expects($this->once())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->once())
            ->method('getEntities')
            ->willReturn(
                $entityCollection = $this->getMockBuilder(EntityCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entityCollection->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $existingImage = $this->getMockBuilder(MediaEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $existingImage->expects($this->any())
            ->method('getFileName')
            ->willReturn($imageName2);
        $this->assertNotEmpty($this->manager->getPreparedMedia($product, $requestEntity));
    }

    public function testGetMediaData()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|ProductMediaEntity $productMediaEntity */
        $productMediaEntity = $this->getMockBuilder(ProductMediaEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $productMediaEntity->expects($this->once())
            ->method('getUniqueIdentifier')
            ->willReturn('some-id');
        $productMediaCollection = new ProductMediaCollection();
        $productMediaCollection->add($productMediaEntity);
        $product->expects($this->once())
            ->method('getMedia')
            ->willReturn($productMediaCollection);
        $productMediaEntity->expects($this->once())
            ->method('getMedia')
            ->willReturn(
                $mediaEntity = $this->getMockBuilder(MediaEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $productMediaEntity->expects($this->any())
            ->method('getId')
            ->willReturn('some-id');
        $mediaEntity->expects($this->once())
            ->method('getId')
            ->willReturn('some-id');
        $mediaEntity->expects($this->once())
            ->method('getFileName')
            ->willReturn('some-file.jpg');
        $this->manager->getMediaData($product);
    }

    public function testCleanOrphans()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|ProductRequestEntity $requestEntity */
        $requestEntity = $this->getMockBuilder(ProductRequestEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->any())
            ->method('getMedia')
            ->willReturn(
                $productMediaCollection = $this->getMockBuilder(ProductMediaCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $productMediaCollection->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $this->getMockBuilder(ProductMediaEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $this->manager->getPreparedMedia($product, $requestEntity);
        $this->manager->cleanOrphans();
    }
}
