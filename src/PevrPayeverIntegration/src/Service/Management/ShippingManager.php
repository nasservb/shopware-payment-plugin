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

use Payever\ExternalIntegration\Products\Http\RequestEntity\ProductRequestEntity;
use Shopware\Core\Content\Product\ProductEntity;

class ShippingManager
{
    public const SIZE_MILLIMETER = 'mm';
    public const SIZE_CENTIMETER = 'cm';
    public const SIZE_METER = 'm';
    public const MASS_GRAM = 'g';
    public const MASS_KILOGRAM = 'kg';
    public const PAYEVER_MEASURE_SIZE = self::SIZE_CENTIMETER;
    public const PAYEVER_MEASURE_MASS = self::MASS_KILOGRAM;
    public const SHOPWARE_MEASURE_SIZE = self::SIZE_MILLIMETER;
    public const SHOPWARE_MEASURE_MASS = self::MASS_KILOGRAM;

    private const MASS_MULTIPLIER_MAP = [
        self::MASS_GRAM => 0.001,
        self::MASS_KILOGRAM => 1.0,
    ];
    private const SIZE_MULTIPLIER_MAP = [
        self::SIZE_MILLIMETER => 1.0,
        self::SIZE_CENTIMETER => 10.0,
        self::SIZE_METER => 1000.0,
    ];
    private const KEY_MEASURE_SIZE = 'measure_size';
    private const KEY_MEASURE_MASS = 'measure_mass';
    private const KEY_WIDTH = 'width';
    private const KEY_LENGTH = 'length';
    private const KEY_HEIGHT = 'height';
    private const KEY_WEIGHT = 'weight';

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getShipping(ProductEntity $product): array
    {
        $sizeMultiplier = $this->getSizeMultiplier(self::PAYEVER_MEASURE_SIZE);
        $massMultiplier = $this->getMassMultiplier(self::PAYEVER_MEASURE_MASS);

        return [
            self::KEY_MEASURE_SIZE => self::PAYEVER_MEASURE_SIZE,
            self::KEY_MEASURE_MASS => self::PAYEVER_MEASURE_MASS,
            self::KEY_WIDTH => (float) $product->getWidth() / $sizeMultiplier,
            self::KEY_LENGTH => (float) $product->getLength() / $sizeMultiplier,
            self::KEY_HEIGHT => (float) $product->getHeight() / $sizeMultiplier,
            self::KEY_WEIGHT => (float) $product->getWeight() / $massMultiplier,
        ];
    }

    /**
     * @param ProductEntity $product
     * @param ProductRequestEntity $requestEntity
     */
    public function setShipping(ProductEntity $product, ProductRequestEntity $requestEntity)
    {
        $shipping = $requestEntity->getShipping();
        if ($shipping) {
            $sizeMultiplier = $this->getSizeMultiplier($shipping->getMeasureSize() ?: self::PAYEVER_MEASURE_SIZE);
            $massMultiplier = $this->getMassMultiplier($shipping->getMeasureMass() ?: self::PAYEVER_MEASURE_MASS);
            $product->setWidth((float) $shipping->getWidth() * $sizeMultiplier);
            $product->setLength((float) $shipping->getLength() * $sizeMultiplier);
            $product->setHeight((float) $shipping->getHeight() * $sizeMultiplier);
            $product->setWeight((float) $shipping->getWeight() * $massMultiplier);
        }
    }

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getShippingData(ProductEntity $product): array
    {
        $sizeMultiplier = $this->getSizeMultiplier(self::SHOPWARE_MEASURE_SIZE);
        $massMultiplier = $this->getMassMultiplier(self::SHOPWARE_MEASURE_MASS);

        return [
            self::KEY_WIDTH => $product->getWidth() * $sizeMultiplier,
            self::KEY_LENGTH => $product->getLength() * $sizeMultiplier,
            self::KEY_HEIGHT => $product->getHeight() * $sizeMultiplier,
            self::KEY_WEIGHT => $product->getWeight() * $massMultiplier,
        ];
    }

    /**
     * @param string $measureSize
     * @return float
     */
    private function getSizeMultiplier(string $measureSize): float
    {
        return self::SIZE_MULTIPLIER_MAP[$measureSize] ?? 1.0;
    }

    /**
     * @param string $measureMass
     * @return float
     */
    private function getMassMultiplier(string $measureMass): float
    {
        return self::MASS_MULTIPLIER_MAP[$measureMass] ?? 1.0;
    }
}
