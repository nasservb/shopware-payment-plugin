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

namespace Payever\PayeverPayments\Service\Management;

use Payever\ExternalIntegration\Products\Http\MessageEntity\ProductCategoryEntity;
use Payever\ExternalIntegration\Products\Http\RequestEntity\ProductRequestEntity;
use Payever\PayeverPayments\Service\Helper\SeoHelper;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class CategoryManager
{
    use \Payever\PayeverPayments\Service\DataAbstractionLayer\GenericTrait;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $cmsPageRepository;

    /**
     * @var SeoHelper
     */
    protected $seoHelper;

    /**
     * @param EntityRepositoryInterface $categoryRepository
     * @param EntityRepositoryInterface $cmsPageRepository
     * @param SeoHelper $seoHelper
     */
    public function __construct(
        EntityRepositoryInterface $categoryRepository,
        EntityRepositoryInterface $cmsPageRepository,
        SeoHelper $seoHelper
    ) {
        $this->categoryRepository = $categoryRepository;
        $this->cmsPageRepository = $cmsPageRepository;
        $this->seoHelper = $seoHelper;
    }

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getCategoryNames(ProductEntity $product): array
    {
        $categoryNames = [];
        $categoryCollection = $product->getCategories();
        if ($categoryCollection) {
            foreach ($categoryCollection->getElements() as $categoryEntity) {
                $categoryName = $categoryEntity->getName();
                if ($categoryName) {
                    $categoryNames[] = $categoryName;
                }
            }
        }

        return $categoryNames;
    }

    /**
     * @param ProductEntity $product
     * @param ProductRequestEntity $requestEntity
     * @return CategoryCollection
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getPreparedCategoryCollection(
        ProductEntity $product,
        ProductRequestEntity $requestEntity
    ): CategoryCollection {
        $collection = new CategoryCollection();
        $requestEntities = $requestEntity->getCategories();
        $this->processAssignedCategories($collection, $product, $requestEntities);
        $this->processExistingCategories($collection, $requestEntities);
        if ($requestEntities) {
            /** @var CategoryEntity|null $rootCategory */
            $rootCategory = $this->categoryRepository->search(
                (new Criteria())->addFilter(new EqualsFilter('parentId', null))->setLimit(1),
                $this->getContext()
            )
                ->getEntities()
                ->first();
            if ($rootCategory) {
                foreach ($requestEntities as $requestCategoryEntity) {
                    $categoryName = $this->getRequestCategoryEntityName($requestCategoryEntity);
                    if ($categoryName) {
                        $childCategory = new CategoryEntity();
                        $data = [
                            'id' => $this->getRandomHex(),
                            'parentId' => $rootCategory->getId(),
                            'type' => 'page',
                            'name' => $categoryName,
                        ];
                        $seoUrlEntity = $this->seoHelper->getSeoUrlCollection($categoryName)->first();
                        if ($seoUrlEntity) {
                            $data['seoUrls'][] = $this->seoHelper->getSeoUrlData($seoUrlEntity);
                        }
                        $cmsPage = $this->getCmsPage();
                        if ($cmsPage) {
                            $data['cmsPageId'] = $cmsPage->getId();
                        }
                        $childCategory->assign($data);
                        $collection->add($childCategory);
                        $this->categoryRepository->upsert([$data], $this->getContext());
                    }
                }
            }
        }

        return $collection;
    }

    /**
     * @param CategoryCollection $collection
     * @param ProductEntity $product
     * @param array $requestEntities
     */
    private function processAssignedCategories(
        CategoryCollection $collection,
        ProductEntity $product,
        array &$requestEntities
    ): void {
        $assignedCategories = $product->getCategories();
        if (!$assignedCategories) {
            return;
        }
        foreach ($assignedCategories as $assignedCategory) {
            foreach ($requestEntities as $key => $requestCategoryEntity) {
                if ($this->getRequestCategoryEntityName($requestCategoryEntity) === $assignedCategory->getName()) {
                    unset($requestEntities[$key]);
                    break;
                }
            }
            $collection->add($assignedCategory);
        }
    }

    /**
     * @param CategoryCollection $collection
     * @param array $requestEntities
     */
    private function processExistingCategories(CategoryCollection $collection, array &$requestEntities): void
    {
        $categoryNames = [];
        foreach ($requestEntities as $requestCategoryEntity) {
            $categoryName = $this->getRequestCategoryEntityName($requestCategoryEntity);
            if ($categoryName) {
                $categoryNames[] = $categoryName;
            }
        }
        if (!$categoryNames) {
            return;
        }
        $existingCategories = $this->categoryRepository->search(
            (new Criteria())->addFilter(new EqualsAnyFilter('name', $categoryNames)),
            $this->getContext()
        )
            ->getEntities()
            ->getElements();
        /** @var CategoryEntity $existingCategory */
        foreach ($existingCategories as $existingCategory) {
            foreach ($requestEntities as $key => $requestCategoryEntity) {
                if ($this->getRequestCategoryEntityName($requestCategoryEntity) === $existingCategory->getName()) {
                    unset($requestEntities[$key]);
                }
            }
            $collection->add($existingCategory);
        }
    }

    /**
     * @param ProductCategoryEntity|string $requestCategoryEntity
     * @return string|null
     */
    private function getRequestCategoryEntityName($requestCategoryEntity): ?string
    {
        return $requestCategoryEntity instanceof ProductCategoryEntity
            ? $requestCategoryEntity->getTitle()
            : $requestCategoryEntity;
    }

    /**
     * @return CmsPageEntity|null
     */
    private function getCmsPage(): ?CmsPageEntity
    {
        return $this->cmsPageRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('name', 'Default category layout')),
            $this->getContext()
        )
            ->getEntities()
            ->first();
    }

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getCategoriesData(ProductEntity $product): array
    {
        $data = [];
        $categoryCollection = $product->getCategories();
        if ($categoryCollection) {
            foreach ($categoryCollection as $categoryEntity) {
                $data[] = [
                    'id' => $categoryEntity->getId(),
                ];
            }
        }

        return $data;
    }
}
