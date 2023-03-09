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

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class ManufacturerManager
{
    use \Payever\PayeverPayments\Service\DataAbstractionLayer\GenericTrait;

    /**
     * @var EntityRepositoryInterface
     */
    private $manufacturerRepository;

    /**
     * @param EntityRepositoryInterface $manufacturerRepository
     */
    public function __construct(EntityRepositoryInterface $manufacturerRepository)
    {
        $this->manufacturerRepository = $manufacturerRepository;
    }

    /**
     * @param ProductEntity $product
     * @return ProductManufacturerEntity
     */
    public function getPreparedManufacturer(ProductEntity $product): ProductManufacturerEntity
    {
        $manufacturer = $product->getManufacturer();
        if (!$manufacturer) {
            $manufacturerName = 'shopware AG';
            /** @var ProductManufacturerEntity|null $manufacturerTranslation */
            $manufacturer = $this->manufacturerRepository->search(
                (new Criteria())
                    ->addFilter(new EqualsFilter('name', $manufacturerName)),
                $this->getContext()
            )
                ->getEntities()
                ->first();
            if (!$manufacturer) {
                $manufacturer = new ProductManufacturerEntity();
                $manufacturer->assign(
                    $manufacturerData = [
                        'id' => $this->getRandomHex(),
                        'name' => $manufacturerName,
                    ]
                );
                $this->manufacturerRepository->upsert([$manufacturerData], $this->getContext());
            }
        }

        return $manufacturer;
    }

    /**
     * @param ProductEntity $product
     * @return array|null
     */
    public function getManufacturerData(ProductEntity $product): ?array
    {
        $data = null;
        $manufacturer = $product->getManufacturer();
        if ($manufacturer) {
            $data = ['id' => $manufacturer->getId()];
        }

        return $data;
    }
}
