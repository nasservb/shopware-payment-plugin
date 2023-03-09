<?php

namespace Payever\PayeverPayments\tests\unit\Service\Management;

use Payever\ExternalIntegration\Products\Http\MessageEntity\ProductVariantOptionEntity;
use Payever\ExternalIntegration\Products\Http\RequestEntity\ProductRequestEntity;
use Payever\PayeverPayments\Service\Management\OptionManager;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation\PropertyGroupOptionTranslationEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupTranslation\PropertyGroupTranslationEntity;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class OptionManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $propertyGroupRepository;

    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $propertyGroupTranslationRepository;

    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $propertyGroupOptionRepository;

    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $propertyGroupOptionTranslationRepository;

    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $configuratorRepository;

    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $productOptionRepository;

    /**
     * @var OptionManager
     */
    private $manager;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->propertyGroupRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->propertyGroupTranslationRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->propertyGroupOptionRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->propertyGroupOptionTranslationRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configuratorRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->productOptionRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->manager = new OptionManager(
            $this->propertyGroupRepository,
            $this->propertyGroupTranslationRepository,
            $this->propertyGroupOptionRepository,
            $this->propertyGroupOptionTranslationRepository,
            $this->configuratorRepository,
            $this->productOptionRepository
        );
    }

    public function testGetOptions()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getOptions')
            ->willReturn(
                $propertyGroupOptionCollection = $this->getMockBuilder(PropertyGroupOptionCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $propertyGroupOptionCollection->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $propertyGroupOptionEntity = $this->getMockBuilder(PropertyGroupOptionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $propertyGroupOptionEntity->expects($this->once())
            ->method('getGroup')
            ->willReturn(
                $group = $this->getMockBuilder(PropertyGroupEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $group->expects($this->once())
            ->method('getName')
            ->willReturn('some-name');
        $propertyGroupOptionEntity->expects($this->once())
            ->method('getName')
            ->willReturn('some-value-name');
        $this->assertNotEmpty($this->manager->getOptions($product));
    }

    public function testCaptureOrphans()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->any())
            ->method('getConfiguratorSettings')
            ->willReturn(
                $this->getMockBuilder(ProductConfiguratorSettingCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
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
                $this->getMockBuilder(ProductEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $this->manager->captureOrphans($product);
    }

    public function testGetPreparedOptionCollection()
    {
        /** @var MockObject|ProductRequestEntity $requestEntity */
        $requestEntity = $this->getMockBuilder(ProductRequestEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getOptions'])
            ->getMock();
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->any())
            ->method('getParent')
            ->willReturn(
                $this->getMockBuilder(ProductEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $requestEntity->expects($this->once())
            ->method('getOptions')
            ->willReturn([
                $option = $this->getMockBuilder(ProductVariantOptionEntity::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getName', 'getValue'])
                    ->getMock()
            ]);
        $option->expects($this->once())
            ->method('getName')
            ->willReturn('name1');
        $option->expects($this->once())
            ->method('getValue')
            ->willReturn('value1');
        $this->propertyGroupTranslationRepository->expects($this->once())
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
                $propertyGroupTranslation = $this->getMockBuilder(PropertyGroupTranslationEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $propertyGroupTranslation->expects($this->once())
            ->method('getPropertyGroup')
            ->willReturn(
                $this->getMockBuilder(PropertyGroupEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->propertyGroupOptionTranslationRepository->expects($this->once())
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
                $propertyOptionGroupTranslation = $this->getMockBuilder(
                    PropertyGroupOptionTranslationEntity::class
                )
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $propertyOptionGroupTranslation->expects($this->once())
            ->method('getPropertyGroupOption')
            ->willReturn(
                $this->getMockBuilder(PropertyGroupOptionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->assertNotEmpty($this->manager->getPreparedOptionCollection($requestEntity, $product));
    }

    public function testGetPreparedOptionCollectionCaseNew()
    {
        /** @var MockObject|ProductRequestEntity $requestEntity */
        $requestEntity = $this->getMockBuilder(ProductRequestEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getOptions'])
            ->getMock();
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->any())
            ->method('getParent')
            ->willReturn(
                $this->getMockBuilder(ProductEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $requestEntity->expects($this->once())
            ->method('getOptions')
            ->willReturn([
                $option = $this->getMockBuilder(ProductVariantOptionEntity::class)
                    ->disableOriginalConstructor()
                    ->addMethods(['getName', 'getValue'])
                    ->getMock()
            ]);
        $option->expects($this->once())
            ->method('getName')
            ->willReturn('name1');
        $option->expects($this->once())
            ->method('getValue')
            ->willReturn('value1');
        $this->propertyGroupTranslationRepository->expects($this->once())
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
        $this->propertyGroupOptionTranslationRepository->expects($this->once())
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
        $this->assertNotEmpty($this->manager->getPreparedOptionCollection($requestEntity, $product));
    }

    public function testGetOptionsData()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getOptions')
            ->willReturn(
                $propertyGroupOptionCollection = $this->getMockBuilder(PropertyGroupOptionCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $propertyGroupOptionCollection->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $this->getMockBuilder(PropertyGroupOptionEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $this->assertNotEmpty($this->manager->getOptionsData($product));
    }

    public function testGetProductConfiguratorSettingData()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getConfiguratorSettings')
            ->willReturn(
                $productConfiguratorSettingsCollection = $this->getMockBuilder(
                    ProductConfiguratorSettingCollection::class
                )
                ->disableOriginalConstructor()
                ->getMock()
            );
        $productConfiguratorSettingsCollection->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $this->getMockBuilder(ProductConfiguratorSettingEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $this->assertNotEmpty($this->manager->getProductConfiguratorSettingData($product));
    }

    public function testCleanOrphans()
    {
        /** @var MockObject|ProductEntity $product */
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $product->expects($this->once())
            ->method('getConfiguratorSettings')
            ->willReturn(
                $configurationSettings = $this->getMockBuilder(ProductConfiguratorSettingCollection::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $configurationSettings->expects($this->once())
            ->method('getElements')
            ->willReturn([
                $this->getMockBuilder(ProductConfiguratorSettingEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $this->configuratorRepository->expects($this->once())
            ->method('delete');
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
            ->willReturn('some-id');
        $child->expects($this->once())
            ->method('getOptionIds')
            ->willReturn(['some-option-id']);
        $this->productOptionRepository->expects($this->once())
            ->method('delete');
        $this->manager->captureOrphans($product);
        $this->manager->cleanOrphans($product);
    }
}
