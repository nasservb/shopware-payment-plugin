<?php

/**
 * payever GmbH
 *
 * NOTICE OF LICENSE
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade payever Shopware package
 * to newer versions in the future.
 *
 * @category    Payever
 * @author      payever GmbH <service@payever.de>
 * @copyright   Copyright (c) 2021 payever GmbH (http://www.payever.de)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Payever\PayeverPayments\Service\Transformer;

use Payever\ExternalIntegration\Products\Enum\ProductTypeEnum;
use Payever\ExternalIntegration\Products\Http\RequestEntity\ProductRemovedRequestEntity;
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
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;

class ProductTransformer
{
    use ProductAwareTrait;

    /**
     * @var CategoryManager
     */
    private $categoryManager;

    /**
     * @var GalleryManager
     */
    private $galleryManager;

    /**
     * @var PriceManager
     */
    private $priceManager;

    /**
     * @var ShippingManager
     */
    private $shippingManager;

    /**
     * @var OptionManager
     */
    private $optionManager;

    /**
     * @var ManufacturerManager
     */
    private $manufacturerManager;

    /**
     * @var VisibilityManager
     */
    private $visibilityManager;

    /**
     * @var ConfigHelper
     */
    private $configHelper;

    /**
     * @var SeoHelper
     */
    private $seoHelper;

    /**
     * @param EntityRepositoryInterface $productRepository
     * @param CategoryManager $categoryManager
     * @param GalleryManager $galleryManager
     * @param PriceManager $priceManager
     * @param ShippingManager $shippingManager
     * @param OptionManager $optionManager
     * @param ManufacturerManager $manufacturerManager
     * @param VisibilityManager $visibilityManager
     * @param ConfigHelper $configHelper
     * @param SeoHelper $seoHelper
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        EntityRepositoryInterface $productRepository,
        CategoryManager $categoryManager,
        GalleryManager $galleryManager,
        PriceManager $priceManager,
        ShippingManager $shippingManager,
        OptionManager $optionManager,
        ManufacturerManager $manufacturerManager,
        VisibilityManager $visibilityManager,
        ConfigHelper $configHelper,
        SeoHelper $seoHelper
    ) {
        $this->productRepository = $productRepository;
        $this->categoryManager = $categoryManager;
        $this->galleryManager = $galleryManager;
        $this->priceManager = $priceManager;
        $this->shippingManager = $shippingManager;
        $this->optionManager = $optionManager;
        $this->manufacturerManager = $manufacturerManager;
        $this->visibilityManager = $visibilityManager;
        $this->configHelper = $configHelper;
        $this->seoHelper = $seoHelper;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return ProductTypeEnum::TYPE_PHYSICAL;
    }

    /**
     * @param ProductEntity $product
     * @return ProductRequestEntity
     */
    public function transformFromShopwareIntoPayever(ProductEntity $product): ProductRequestEntity
    {
        $productRequestEntity = new ProductRequestEntity();
        $this->fillProductRequestEntityFromProduct($productRequestEntity, $product);
        $productRequestEntity->setVariants($this->getProductVariants($product));

        return $productRequestEntity;
    }

    /**
     * @param ProductRequestEntity $productRequestEntity
     * @param ProductEntity $product
     * @SuppressWarnings(PHPMD.ElseExpression)
     */
    private function fillProductRequestEntityFromProduct(
        ProductRequestEntity $productRequestEntity,
        ProductEntity $product
    ): void {
        $parent = $product->getParent();
        $productRequestEntity->setExternalId($this->configHelper->getProductsSyncExternalId())
            ->setBusinessUuid($this->configHelper->getBusinessUuid())
            ->setActive($parent ? true : $product->getActive())
            ->setType($this->getType())
            ->setSku($sku = $product->getProductNumber())
            ->setTitle($product->getName() ?? $sku)
            ->setDescription($product->getDescription() ?? $sku)
            ->setCategories($this->categoryManager->getCategoryNames($product))
            ->setImages($this->galleryManager->getImages($product))
            ->setCurrency($this->priceManager->getCurrencyIsoCode())
            ->setVatRate($this->priceManager->getVatRate($product))
            ->setShipping($this->shippingManager->getShipping($product));
        $priceDonor = $product;
        if ($parent && !$this->priceManager->hasPrice($product)) {
            $priceDonor = $parent;
        }
        if ($this->priceManager->getLinked($priceDonor)) {
            $productRequestEntity->setPrice($this->priceManager->getGrossPrice($priceDonor));
        } else {
            $productRequestEntity->setPrice($this->priceManager->getCalculatedGrossPrice($priceDonor))
                ->setSalePrice($this->priceManager->getGrossPrice($priceDonor))
                ->setOnSales(true);
        }
    }

    /**
     * @param ProductEntity $product
     * @return array
     */
    private function getProductVariants(ProductEntity $product): array
    {
        $variants = [];
        $productCollection = $product->getChildren();
        if ($productCollection) {
            foreach ($productCollection as $childProduct) {
                $productRequestEntity = new ProductRequestEntity();
                if (!$childProduct->getParent()) {
                    $childProduct->setParent($product);
                }
                $this->fillProductRequestEntityFromProduct($productRequestEntity, $childProduct);
                $variants[] = $productRequestEntity->setOptions($this->optionManager->getOptions($childProduct));
            }
        }

        return $variants;
    }

    /**
     * @param ProductRequestEntity $requestEntity
     * @return ProductEntity
     * @throws \Exception
     */
    public function transformFromPayeverIntoShopwareProduct(ProductRequestEntity $requestEntity): ProductEntity
    {
        $mainSku = $requestEntity->getSku();
        /** @var ProductEntity $product */
        $product = $this->getProduct($mainSku, true);
        $product->setProductNumber($mainSku);
        $this->fillProductFromRequestEntity($requestEntity, $product);
        $this->optionManager->captureOrphans($product);
        $variants = $requestEntity->getVariants();
        if ($variants) {
            $childrenCollection = $product->getChildren();
            if (!$childrenCollection) {
                $childrenCollection = new ProductCollection();
            }
            foreach ($variants as $variantRequestEntity) {
                if (!$variantRequestEntity->getVatRate()) {
                    $variantRequestEntity->setVatRate($requestEntity->getVatRate());
                }
                $sku = $variantRequestEntity->getSku();
                /** @var ProductEntity $variantProduct */
                $variantProduct = $this->getProduct($sku, true);
                $variantProduct->setProductNumber($sku);
                $this->fillProductFromRequestEntity($variantRequestEntity, $variantProduct);
                $variantProduct->setParentId($product->getId());
                $variantProduct->setParent($product);
                $variantProduct->setOptions(
                    $this->optionManager->getPreparedOptionCollection($variantRequestEntity, $variantProduct)
                );
                $childrenCollection->add($variantProduct);
            }
            $product->setChildren($childrenCollection);
        }

        return $product;
    }

    /**
     * @param ProductRequestEntity $requestEntity
     * @param ProductEntity $product
     * @throws \Exception
     */
    private function fillProductFromRequestEntity(
        ProductRequestEntity $requestEntity,
        ProductEntity $product
    ): void {
        $isVariant = $requestEntity->isVariant();
        $title = (string) $requestEntity->getTitle();
        if (!$title && $isVariant) {
            $optionValues = [];
            $options = $requestEntity->getOptions();
            foreach ($options as $optionEntity) {
                $optionValues[] = $optionEntity->getValue();
            }
            $title = implode(', ', $optionValues);
        }
        $product->setActive((bool) $requestEntity->getActive());
        $product->setName($title);
        $product->setDescription($requestEntity->getDescription());
        if (!$isVariant) {
            $product->setSeoUrls($this->seoHelper->getSeoUrlCollection($title));
        }
        $product->setCategories($this->categoryManager->getPreparedCategoryCollection($product, $requestEntity));
        $product->setMedia($this->galleryManager->getPreparedMedia($product, $requestEntity));
        $product->setPrice($this->priceManager->getPreparedPriceCollection($product, $requestEntity));
        $product->setPrices($this->priceManager->getPreparedProductPriceCollection($product, $requestEntity));
        $product->setTax($this->priceManager->getPreparedTax($requestEntity));
        $this->shippingManager->setShipping($product, $requestEntity);
        $product->setManufacturer($this->manufacturerManager->getPreparedManufacturer($product));
        $product->setVisibilities($this->visibilityManager->getPreparedVisibilityCollection($product));
    }

    /**
     * @param ProductEntity $product
     * @param bool $variantMode
     * @return array
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.IfStatementAssignment)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getProductData(ProductEntity $product, bool $variantMode = false): array
    {
        $isActive = $product->getActive();
        $data = [
            'id' => $product->getId(),
            'active' => $isActive,
            'isCloseout' => true,
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'stock' => $product->getStock(),
            'productNumber' => $product->getProductNumber(),
        ];
        $visibilityData = $this->visibilityManager->getVisibilityData($product);
        if ($isActive && $visibilityData) {
            $data['visibilities'] = $visibilityData;
        }
        $manufacturerData = $this->manufacturerManager->getManufacturerData($product);
        if ($manufacturerData) {
            $data['manufacturer'] = $manufacturerData;
        }
        $seoUrlsData = $this->seoHelper->getSeoUrlDataByProduct($product);
        if ($seoUrlsData) {
            $data['seoUrls'] = [$seoUrlsData];
        }
        $categoriesData = $this->categoryManager->getCategoriesData($product);
        if ($categoriesData) {
            $data['categories'] = $categoriesData;
        }
        $media = $this->galleryManager->getMediaData($product);
        if ($media) {
            $data['media'] = $media;
            $data['cover'] = reset($media);
        }
        $this->galleryManager->cleanOrphans();
        $priceData = $this->priceManager->getPriceData($product);
        if ($priceData) {
            $data['price'] = $priceData;
        }
        $pricesData = $this->priceManager->getPricesData($product);
        if ($pricesData) {
            $data['prices'] = $pricesData;
        }
        $taxData = $this->priceManager->getTaxData($product);
        if ($taxData) {
            $data['tax'] = $taxData;
        }
        if (!$variantMode) {
            $childrenCollection = $product->getChildren();
            if ($childrenCollection) {
                $childrenData = [];
                foreach ($childrenCollection as $child) {
                    $childrenData[] = $this->getProductData($child, true);
                }
                if ($childrenData) {
                    $data['children'] = $childrenData;
                }
            }
            $productConfiguratorSettingData = $this->optionManager->getProductConfiguratorSettingData($product);
            if ($productConfiguratorSettingData) {
                $data['configuratorSettings'] = $productConfiguratorSettingData;
            }
            $this->optionManager->cleanOrphans($product);
        } elseif ($optionsData = $this->optionManager->getOptionsData($product)) {
            $data['options'] = $optionsData;
        }
        $data = array_merge(
            $data,
            $this->shippingManager->getShippingData($product)
        );

        return $data;
    }

    /**
     * Delegates method
     * @param array $data
     */
    public function upsert(array $data): void
    {
        $this->productRepository->upsert($data, $this->getContext());
    }

    /**
     * @param ProductEntity $product
     */
    public function remove(ProductEntity $product): void
    {
        $childrenCollection = $product->getChildren();
        if ($childrenCollection) {
            $childIds = [];
            foreach ($childrenCollection->getElements() as $child) {
                $childIds[] = ['id' => $child->getId()];
            }
            if ($childIds) {
                $this->productRepository->delete($childIds, $this->getContext());
            }
        }
        $this->productRepository->delete(
            [['id' => $product->getId()]],
            $this->getContext()
        );
    }

    /**
     * @param ProductEntity $product
     * @return ProductRemovedRequestEntity
     */
    public function transformRemovedShopwareIntoPayever(ProductEntity $product): ProductRemovedRequestEntity
    {
        return (new ProductRemovedRequestEntity())
            ->setExternalId($this->configHelper->getProductsSyncExternalId())
            ->setSku($product->getProductNumber());
    }
}
