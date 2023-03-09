<?php

namespace Payever\PayeverPayments\tests\unit\Service\Transformer;

use Payever\ExternalIntegration\Products\Http\RequestEntity\ProductRequestEntity;
use Payever\PayeverPayments\Service\Helper\ConfigHelper;
use Payever\PayeverPayments\Service\Helper\SeoHelper;
use Payever\PayeverPayments\Service\Management\CategoryManager;
use Payever\PayeverPayments\Service\Management\GalleryManager;
use Payever\PayeverPayments\Service\Management\ManufacturerManager;
use Payever\PayeverPayments\Service\Management\OptionManager;
use Payever\PayeverPayments\Service\Management\PriceManager;
use Payever\PayeverPayments\Service\Management\ShippingManager;
use Payever\PayeverPayments\Service\Management\VisibilityManager;
use Payever\PayeverPayments\Service\Transformer\ProductTransformer;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Tax\TaxEntity;

class ProductTransformerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var MockObject|CategoryManager
     */
    private $categoryManager;

    /**
     * @var MockObject|GalleryManager
     */
    private $galleryManager;

    /**
     * @var MockObject|PriceManager
     */
    private $priceManager;

    /**
     * @var MockObject|ShippingManager
     */
    private $shippingManager;

    /**
     * @var MockObject|OptionManager
     */
    private $optionManager;

    /**
     * @var MockObject|ManufacturerManager
     */
    private $manufacturerManager;

    /**
     * @var MockObject|VisibilityManager
     */
    private $visibilityManager;

    /**
     * @var MockObject|ConfigHelper
     */
    private $configHelper;

    /**
     * @var MockObject|SeoHelper
     */
    private $seoHelper;

    /**
     * @var ProductTransformer
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
        $this->categoryManager = $this->getMockBuilder(CategoryManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->galleryManager = $this->getMockBuilder(GalleryManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->priceManager = $this->getMockBuilder(PriceManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->shippingManager = $this->getMockBuilder(ShippingManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->optionManager = $this->getMockBuilder(OptionManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->manufacturerManager = $this->getMockBuilder(ManufacturerManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->visibilityManager = $this->getMockBuilder(VisibilityManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configHelper = $this->getMockBuilder(ConfigHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->seoHelper = $this->getMockBuilder(SeoHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->transformer = new ProductTransformer(
            $this->productRepository,
            $this->categoryManager,
            $this->galleryManager,
            $this->priceManager,
            $this->shippingManager,
            $this->optionManager,
            $this->manufacturerManager,
            $this->visibilityManager,
            $this->configHelper,
            $this->seoHelper
        );
    }

    public function testGetType()
    {
        $this->assertNotEmpty($this->transformer->getType());
    }

    public function testTransformFromShopwareIntoPayever()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getActive')
            ->willReturn(true);
        $product->expects($this->once())
            ->method('getProductNumber')
            ->willReturn('some-product-number');
        $product->expects($this->once())
            ->method('getName')
            ->willReturn('some-name');
        $product->expects($this->once())
            ->method('getDescription')
            ->willReturn('some-description');
        $this->categoryManager->expects($this->any())
            ->method('getCategoryNames')
            ->willReturn([]);
        $this->galleryManager->expects($this->any())
            ->method('getImages')
            ->willReturn([]);
        $this->priceManager->expects($this->any())
            ->method('getCurrencyIsoCode')
            ->willReturn('EUR');
        $this->priceManager->expects($this->any())
            ->method('getNetPrice')
            ->willReturn(1.0);
        $this->priceManager->expects($this->any())
            ->method('getGrossPrice')
            ->willReturn(1.19);
        $this->priceManager->expects($this->any())
            ->method('getVatRate')
            ->willReturn(19.0);
        $this->shippingManager->expects($this->any())
            ->method('getShipping')
            ->willReturn([]);
        $product->expects($this->once())
            ->method('getChildren')
            ->willReturn($productCollection = new ProductCollection());
        /** @var MockObject|ProductEntity $child */
        $child = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $child->expects($this->once())
            ->method('getUniqueIdentifier')
            ->willReturn($id='some-child-id');
        $productCollection->add($child);
        $this->optionManager->expects($this->once())
            ->method('getOptions')
            ->willReturn([]);
        $this->assertNotEmpty($this->transformer->transformFromShopwareIntoPayever($product));
    }

    public function testTransformFromPayeverIntoShopwareProduct()
    {
        /** @var MockObject|ProductRequestEntity $requestEntity */
        $requestEntity = $this->getMockBuilder(ProductRequestEntity::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSku', 'isVariant'])
            ->addMethods([
                'getTitle',
                'getActive',
                'getDescription',
                'getVariants',
            ])
            ->getMock();
        $requestEntity->expects($this->once())
            ->method('getSku')
            ->willReturn('some-sku');
        $this->productRepository->expects($this->any())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->any())
            ->method('getEntities')
            ->willReturn(
                $this->getMockBuilder(EntityCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $requestEntity->expects($this->any())
            ->method('isVariant')
            ->willReturn(false);
        $requestEntity->expects($this->any())
            ->method('getTitle')
            ->willReturn('some-title');
        $requestEntity->expects($this->any())
            ->method('getActive')
            ->willReturn(true);
        $requestEntity->expects($this->any())
            ->method('getDescription')
            ->willReturn('some-description');
        $this->seoHelper->expects($this->any())
            ->method('getSeoUrlCollection')
            ->willReturn(
                $this->getMockBuilder(SeoUrlCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->categoryManager->expects($this->any())
            ->method('getPreparedCategoryCollection')
            ->willReturn(
                $this->getMockBuilder(CategoryCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->galleryManager->expects($this->any())
            ->method('getPreparedMedia')
            ->willReturn(
                $this->getMockBuilder(ProductMediaCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->priceManager->expects($this->any())
            ->method('getPreparedPriceCollection')
            ->willReturn(
                $this->getMockBuilder(PriceCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->priceManager->expects($this->any())
            ->method('getPreparedProductPriceCollection')
            ->willReturn(
                $this->getMockBuilder(ProductPriceCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->priceManager->expects($this->any())
            ->method('getPreparedTax')
            ->willReturn(
                $this->getMockBuilder(TaxEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->manufacturerManager->expects($this->any())
            ->method('getPreparedManufacturer')
            ->willReturn(
                $this->getMockBuilder(ProductManufacturerEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->visibilityManager->expects($this->any())
            ->method('getPreparedVisibilityCollection')
            ->willReturn(
                $this->getMockBuilder(ProductVisibilityCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $requestEntity->expects($this->once())
            ->method('getVariants')
            ->willReturn([
                $variantRequestEntity = $this->getMockBuilder(ProductRequestEntity::class)
                    ->disableOriginalConstructor()
                    ->onlyMethods(['getSku', 'isVariant'])
                    ->addMethods(['getTitle', 'getActive'])
                    ->getMock()
            ]);
        $variantRequestEntity->expects($this->once())
            ->method('getSku')
            ->willReturn('some-variant-sku');
        $variantRequestEntity->expects($this->any())
            ->method('getTitle')
            ->willReturn('some-title');
        $variantRequestEntity->expects($this->any())
            ->method('getActive')
            ->willReturn(true);
        $this->optionManager->expects($this->once())
            ->method('getPreparedOptionCollection')
            ->willReturn(
                $this->getMockBuilder(PropertyGroupOptionCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->assertNotEmpty($this->transformer->transformFromPayeverIntoShopwareProduct($requestEntity));
    }

    public function testGetProductData()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getActive')
            ->willReturn(true);
        $product->expects($this->once())
            ->method('getId')
            ->willReturn('some-id');
        $product->expects($this->once())
            ->method('getName')
            ->willReturn('some-name');
        $product->expects($this->once())
            ->method('getDescription')
            ->willReturn('some-description');
        $product->expects($this->once())
            ->method('getStock')
            ->willReturn(0);
        $product->expects($this->once())
            ->method('getProductNumber')
            ->willReturn('some-sku');
        $this->visibilityManager->expects($this->any())
            ->method('getVisibilityData')
            ->willReturn(['some' => 'data']);
        $this->manufacturerManager->expects($this->any())
            ->method('getManufacturerData')
            ->willReturn(['some' => 'data']);
        $this->seoHelper->expects($this->any())
            ->method('getSeoUrlDataByProduct')
            ->willReturn(['some' => 'data']);
        $this->categoryManager->expects($this->any())
            ->method('getCategoriesData')
            ->willReturn(['some' => 'data']);
        $this->galleryManager->expects($this->any())
            ->method('getMediaData')
            ->willReturn(['some' => 'data']);
        $this->priceManager->expects($this->any())
            ->method('getPriceData')
            ->willReturn(['some' => 'data']);
        $this->priceManager->expects($this->any())
            ->method('getPricesData')
            ->willReturn(['some' => 'data']);
        $this->priceManager->expects($this->any())
            ->method('getTaxData')
            ->willReturn(['some' => 'data']);
        $product->expects($this->once())
            ->method('getChildren')
            ->willReturn($productCollection = new ProductCollection());
        /** @var MockObject|ProductEntity $child */
        $child = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $child->expects($this->once())
            ->method('getUniqueIdentifier')
            ->willReturn($id='some-child-id');
        $productCollection->add($child);
        $child->expects($this->once())
            ->method('getActive')
            ->willReturn(true);
        $child->expects($this->once())
            ->method('getId')
            ->willReturn('some-id');
        $child->expects($this->once())
            ->method('getName')
            ->willReturn('some-name');
        $child->expects($this->once())
            ->method('getDescription')
            ->willReturn('some-description');
        $child->expects($this->once())
            ->method('getStock')
            ->willReturn(0);
        $child->expects($this->once())
            ->method('getProductNumber')
            ->willReturn('some-sku');
        $this->shippingManager->expects($this->any())
            ->method('getShippingData')
            ->willReturn([]);
        $this->assertNotEmpty($this->transformer->getProductData($product));
    }

    public function testUpsert()
    {
        $this->productRepository->expects($this->once())
            ->method('upsert');
        $this->transformer->upsert(['some' => 'data']);
    }

    public function testRemove()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getChildren')
            ->willReturn(
                $childrenCollection = $this->getMockBuilder(ProductCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $childrenCollection->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $child = $this->getMockBuilder(ProductEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $child->expects($this->once())
            ->method('getId')
            ->willReturn('some-hash');
        $this->productRepository->expects($this->exactly(2))
            ->method('delete');

        $this->transformer->remove($product);
    }

    public function testTransformRemovedShopwareIntoPayever()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configHelper->expects($this->once())
            ->method('getProductsSyncExternalId')
            ->willReturn('some-external-id');
        $product->expects($this->once())
            ->method('getProductNumber')
            ->willReturn('some-sku');
        $this->assertNotEmpty($this->transformer->transformRemovedShopwareIntoPayever($product));
    }
}
