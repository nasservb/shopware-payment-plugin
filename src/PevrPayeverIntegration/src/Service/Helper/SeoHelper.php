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

namespace Payever\PayeverPayments\Service\Helper;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Defaults;

class SeoHelper
{
    use \Payever\PayeverPayments\Service\DataAbstractionLayer\GenericTrait;

    public const TITLE_MAX_LENGTH = 50;

    /**
     * @param string $title
     * @return SeoUrlCollection
     */
    public function getSeoUrlCollection(string $title): SeoUrlCollection
    {
        if (mb_strlen($title) > self::TITLE_MAX_LENGTH) {
            $title = mb_substr($title, 0, self::TITLE_MAX_LENGTH);
        }
        $collection = new SeoUrlCollection();
        $seoUrlEntity = new SeoUrlEntity();
        $seoUrlEntity->setId($this->getRandomHex());
        $seoUrlEntity->setLanguageId(Defaults::LANGUAGE_SYSTEM);
        $seoUrlEntity->setRouteName($title);
        $seoUrlEntity->setPathInfo($title);
        $seoUrlEntity->setSeoPathInfo($title);
        $collection->add($seoUrlEntity);

        return $collection;
    }

    /**
     * @param ProductEntity $product
     * @return array|null
     */
    public function getSeoUrlDataByProduct(ProductEntity $product): ?array
    {
        $data = null;
        $seoUrlCollection = $product->getSeoUrls();
        if ($seoUrlCollection) {
            $seoUrlEntity = $seoUrlCollection->first();
            if ($seoUrlEntity) {
                $data = $this->getData($seoUrlEntity);
            }
        }

        return $data;
    }

    /**
     * @param SeoUrlEntity|null $seoUrlEntity
     * @return array|null
     */
    public function getSeoUrlData(SeoUrlEntity $seoUrlEntity = null): ?array
    {
        $data = null;
        if ($seoUrlEntity) {
            $data = $this->getData($seoUrlEntity);
        }

        return $data;
    }

    /**
     * @param SeoUrlEntity $seoUrlEntity
     * @return array
     */
    private function getData(SeoUrlEntity $seoUrlEntity): array
    {
        return [
            'id' => $seoUrlEntity->getId(),
            'languageId' => $seoUrlEntity->getLanguageId(),
            'routeName' => $seoUrlEntity->getRouteName(),
            'pathInfo' => $seoUrlEntity->getPathInfo(),
            'seoPathInfo' => $seoUrlEntity->getSeoPathInfo(),
        ];
    }
}
