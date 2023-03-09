<?php

namespace Payever\PayeverPayments\tests\unit\Service\Management;

use Payever\ExternalIntegration\Products\Http\MessageEntity\ProductCategoryEntity;
use Payever\ExternalIntegration\Products\Http\RequestEntity\ProductRequestEntity;
use Payever\PayeverPayments\Service\Helper\SeoHelper;
use Payever\PayeverPayments\Service\Management\CategoryManager;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class CategoryManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $cmsPageRepository;

    /**
     * @var MockObject|SeoHelper
     */
    protected $seoHelper;

    /**
     * @var CategoryManager
     */
    protected $manager;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->categoryRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->cmsPageRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->seoHelper = $this->getMockBuilder(SeoHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->manager = new CategoryManager(
            $this->categoryRepository,
            $this->cmsPageRepository,
            $this->seoHelper
        );
    }

    public function testGetCategoryNames()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getCategories')
            ->willReturn(
                $categoryCollection = $this->getMockBuilder(CategoryCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $categoryCollection->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $categoryEntity = $this->getMockBuilder(CategoryEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $categoryEntity->expects($this->once())
            ->method('getName')
            ->willReturn('some-category-name');
        $this->assertNotEmpty($this->manager->getCategoryNames($product));
    }

    public function testGetPreparedCategoryCollection()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|ProductRequestEntity $requestEntity */
        $requestEntity = $this->getMockBuilder(ProductRequestEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getCategories'])
            ->getMock();
        $requestEntity->expects($this->once())
            ->method('getCategories')
            ->willReturn([
                $requestCategoryEntity1 = $this->getMockBuilder(ProductCategoryEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock(),
                $requestCategoryEntity2 = $this->getMockBuilder(ProductCategoryEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock(),
                $requestCategoryEntity3 = $this->getMockBuilder(ProductCategoryEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $product->expects($this->once())
            ->method('getCategories')
            ->willReturn($assignedCategories = new CategoryCollection());
        $assignedCategories->add(
            $assignedCategory = $this->getMockBuilder(CategoryEntity::class)
                ->disableOriginalConstructor()
                ->getMock()
        );
        $assignedCategory->expects($this->once())
            ->method('getUniqueIdentifier')
            ->willReturn('some-id');
        $requestCategoryEntity1->expects($this->any())
            ->method('__call')
            ->willReturn($title1 = 'title1');
        $assignedCategory->expects($this->once())
            ->method('getName')
            ->willReturn($title1);
        $this->categoryRepository->expects($this->any())
            ->method('search')
            ->willReturn(
                $entitySearchResult = $this->getMockBuilder(EntitySearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entitySearchResult->expects($this->any())
            ->method('getEntities')
            ->willReturn(
                $entityCollection = $this->getMockBuilder(EntityCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $entityCollection->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $existingCategory = $this->getMockBuilder(CategoryEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $requestCategoryEntity2->expects($this->any())
            ->method('__call')
            ->willReturn($title2 = 'title2');
        $existingCategory->expects($this->any())
            ->method('getName')
            ->willReturn($title2);
        $entityCollection->expects($this->once())
            ->method('first')
            ->willReturn(
                $rootCategory = $this->getMockBuilder(CategoryEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $requestCategoryEntity3->expects($this->any())
            ->method('__call')
            ->willReturn('title3');
        $this->seoHelper->expects($this->once())
            ->method('getSeoUrlCollection')
            ->willReturn(
                $seoUrlCollection = $this->getMockBuilder(SeoUrlCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $seoUrlCollection->expects($this->once())
            ->method('first')
            ->willReturn(
                $this->getMockBuilder(SeoUrlEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->seoHelper->expects($this->once())
            ->method('getSeoUrlData')
            ->willReturn(['some' => 'data']);
        $this->cmsPageRepository->expects($this->once())
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
            ->method('first')
            ->willReturn(
                $cmsPage = $this->getMockBuilder(CmsPageEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $cmsPage->expects($this->once())
            ->method('getId')
            ->willReturn('some-id');
        $this->assertNotEmpty($this->manager->getPreparedCategoryCollection($product, $requestEntity));
    }

    public function testGetCategoriesData()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getCategories')
            ->willReturn(
                $assignedCategories = new CategoryCollection()
            );
        /** @var MockObject|CategoryEntity $assignedCategory */
        $assignedCategory = $this->getMockBuilder(CategoryEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $assignedCategory->expects($this->once())
            ->method('getUniqueIdentifier')
            ->willReturn($id = 'some-id');
        $assignedCategories->add($assignedCategory);
        $assignedCategory->expects($this->once())
            ->method('getId')
            ->willReturn($id);
        $this->assertNotEmpty($this->manager->getCategoriesData($product));
    }
}
