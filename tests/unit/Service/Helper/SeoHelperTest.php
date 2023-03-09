<?php

namespace Payever\PayeverPayments\tests\unit\Service\Helper;

use Payever\PayeverPayments\Service\Helper\SeoHelper;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;

class SeoHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var SeoHelper
     */
    private $helper;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->helper = new SeoHelper();
    }

    public function testGetSeoUrlCollection()
    {
        $this->assertNotEmpty($this->helper->getSeoUrlCollection(str_repeat('some-title', 100)));
    }

    public function testGetSeoUrlDataByProduct()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getSeoUrls')
            ->willReturn(
                $seoUrlCollection = $this->getMockBuilder(SeoUrlCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $seoUrlCollection->expects($this->once())
            ->method('first')
            ->willReturn(
                $seoUrlEntity = $this->getMockBuilder(SeoUrlEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $seoUrlEntity->expects($this->once())
            ->method('getId')
            ->willReturn('some-id');
        $seoUrlEntity->expects($this->once())
            ->method('getLanguageId')
            ->willReturn('some-language-id');
        $seoUrlEntity->expects($this->once())
            ->method('getRouteName')
            ->willReturn('some-route-name');
        $seoUrlEntity->expects($this->once())
            ->method('getPathInfo')
            ->willReturn('some-path-info');
        $seoUrlEntity->expects($this->once())
            ->method('getSeoPathInfo')
            ->willReturn('some-seo-path-info');
        $this->assertNotEmpty($this->helper->getSeoUrlDataByProduct($product));
    }

    public function testGetSeoUrlData()
    {
        /** @var MockObject|SeoUrlEntity $seoUrlEntity */
        $seoUrlEntity = $this->getMockBuilder(SeoUrlEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $seoUrlEntity->expects($this->once())
            ->method('getId')
            ->willReturn('some-id');
        $seoUrlEntity->expects($this->once())
            ->method('getLanguageId')
            ->willReturn('some-language-id');
        $seoUrlEntity->expects($this->once())
            ->method('getRouteName')
            ->willReturn('some-route-name');
        $seoUrlEntity->expects($this->once())
            ->method('getPathInfo')
            ->willReturn('some-path-info');
        $seoUrlEntity->expects($this->once())
            ->method('getSeoPathInfo')
            ->willReturn('some-seo-path-info');
        $this->assertNotEmpty($this->helper->getSeoUrlData($seoUrlEntity));
    }
}
