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

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class VisibilityManager
{
    use \Payever\PayeverPayments\Service\DataAbstractionLayer\GenericTrait;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $productVisibilityRepository;

    /**
     * @param EntityRepositoryInterface $salesChannelRepository
     * @param EntityRepositoryInterface $productVisibilityRepository
     */
    public function __construct(
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $productVisibilityRepository
    ) {
        $this->salesChannelRepository = $salesChannelRepository;
        $this->productVisibilityRepository = $productVisibilityRepository;
    }

    /**
     * @param ProductEntity $product
     * @return ProductVisibilityCollection
     */
    public function getPreparedVisibilityCollection(ProductEntity $product): ProductVisibilityCollection
    {
        $collection = $product->getVisibilities();
        if (!$collection) {
            $collection = new ProductVisibilityCollection();
        }
        if ($product->getActive()) {
            $found = false;
            /** @var ProductVisibilityEntity $productVisibilityEntity */
            foreach ($collection as $productVisibilityEntity) {
                $found = true;
                $productVisibilityEntity->setVisibility(ProductVisibilityDefinition::VISIBILITY_ALL);
            }
            if (!$found) {
                /** @var SalesChannelEntity[] $channelCollection */
                $channelCollection = $this->salesChannelRepository->search(new Criteria(), $this->getContext())
                    ->getEntities();
                foreach ($channelCollection as $channelEntity) {
                    $productVisibilityEntity = new ProductVisibilityEntity();
                    $productVisibilityEntity->assign(
                        $productVisibilityData = [
                            'id' => $this->getRandomHex(),
                            'productId' => $product->getId(),
                            'salesChannelId' => $channelEntity->getId(),
                            'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                        ]
                    );
                    $collection->add($productVisibilityEntity);
                    if ($product->getCreatedAt()) {
                        $this->productVisibilityRepository->upsert([$productVisibilityData], $this->getContext());
                    }
                }
            }
        }

        return $collection;
    }

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getVisibilityData(ProductEntity $product): array
    {
        $visibilityData = [];
        $collection = $product->getVisibilities();
        if ($collection) {
            /** @var ProductVisibilityEntity $productVisibilityEntity */
            foreach ($collection as $productVisibilityEntity) {
                $visibilityData[] = [
                    'id' => $productVisibilityEntity->getId(),
                    'productId' => $productVisibilityEntity->getProductId(),
                    'salesChannelId' => $productVisibilityEntity->getSalesChannelId(),
                    'visibility' => $productVisibilityEntity->getVisibility(),
                ];
            }
        }

        return $visibilityData;
    }
}
