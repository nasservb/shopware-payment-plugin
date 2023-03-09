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
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class GalleryManager
{
    use \Payever\PayeverPayments\Service\DataAbstractionLayer\GenericTrait;

    /**
     * @var MediaService
     */
    private $mediaService;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepositoryDecorator;

    /**
     * @var EntityRepositoryInterface
     */
    private $productMediaRepository;

    /**
     * @var ProductMediaCollection
     */
    private $capturedProductMediaCollection;

    /**
     * @param MediaService $mediaService
     * @param EntityRepositoryInterface $mediaRepositoryDecorator
     * @param EntityRepositoryInterface $productMediaRepository
     */
    public function __construct(
        MediaService $mediaService,
        EntityRepositoryInterface $mediaRepositoryDecorator,
        EntityRepositoryInterface $productMediaRepository
    ) {
        $this->mediaService = $mediaService;
        $this->mediaRepositoryDecorator = $mediaRepositoryDecorator;
        $this->productMediaRepository = $productMediaRepository;
    }

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getImages(ProductEntity $product): array
    {
        $images = [];
        $productMediaCollection = $product->getMedia();
        if ($productMediaCollection) {
            foreach ($productMediaCollection->getElements() as $productMediaEntity) {
                $mediaEntity = $productMediaEntity->getMedia();
                if ($mediaEntity && $mediaEntity->getMediaType() instanceof ImageType) {
                    $url = $mediaEntity->getUrl();
                    if ($url) {
                        $images[] = $url;
                    }
                }
            }
        }

        return $images;
    }

    /**
     * @param ProductEntity $product
     * @param ProductRequestEntity $requestEntity
     * @return ProductMediaCollection
     */
    public function getPreparedMedia(
        ProductEntity $product,
        ProductRequestEntity $requestEntity
    ): ProductMediaCollection {
        $this->capturedProductMediaCollection = $product->getMedia() ? clone $product->getMedia() : null;
        $collection = new ProductMediaCollection();
        $images = $requestEntity->getImages();
        $imagesUrl = $requestEntity->getImagesUrl();
        if ($images) {
            $existingImages = $this->mediaRepositoryDecorator->search(
                (new Criteria())->addFilter(new EqualsAnyFilter('fileName', $images)),
                $this->getContext()
            )
                ->getEntities()
                ->getElements();
            /** @var MediaEntity $existingImage */
            foreach ($existingImages as $existingImage) {
                foreach ($images as $key => $imageName) {
                    if ($existingImage->getFileName() === $imageName) {
                        unset($images[$key]);
                        $this->appendMediaEntity($collection, $product, $existingImage);
                    }
                }
            }
        }
        if ($images) {
            foreach ($images as $key => $imageName) {
                $imageUrl = $imagesUrl[$key] ?? null;
                if ($imageUrl) {
                    $mediaEntity = new MediaEntity();
                    $data = [
                        'id' => $this->getRandomHex(),
                        'fileName' => $imageName,
                    ];
                    $mediaEntity->assign($data);
                    $this->mediaRepositoryDecorator->upsert(
                        [$data],
                        $this->getContext()
                    );
                    $this->mediaService->saveMediaFile(
                        $this->mediaService->fetchFile($this->createMediaFileRequest($imageUrl)),
                        $imageName,
                        $this->getContext(),
                        null,
                        $mediaEntity->getId()
                    );
                    $this->appendMediaEntity($collection, $product, $mediaEntity);
                }
            }
        }

        return $collection;
    }

    /**
     * @param ProductMediaCollection $collection
     * @param ProductEntity $product
     * @param MediaEntity $mediaEntity
     */
    private function appendMediaEntity(
        ProductMediaCollection $collection,
        ProductEntity $product,
        MediaEntity $mediaEntity
    ): void {
        $productMediaEntity = new ProductMediaEntity();
        $productMediaEntity->setId($this->getRandomHex());
        $productMediaEntity->setMedia($mediaEntity);
        $productMediaEntity->setProduct($product);
        $collection->add($productMediaEntity);
    }

    /**
     * @param string $imageUrl
     * @return Request
     */
    private function createMediaFileRequest(string $imageUrl): Request
    {
        $request = new Request();
        $request->headers = new HeaderBag();
        $request->headers->set('content_type', 'application/json');
        $extension = 'jpg';
        $pointPos = strrpos($imageUrl, '.', strlen($imageUrl) - 5);
        if (false !== $pointPos) {
            $extension = substr($imageUrl, $pointPos + 1);
        }
        $request->query = new ParameterBag([
            'extension' => $extension,
        ]);
        $request->request = new ParameterBag([
            'url' => $imageUrl,
        ]);

        return $request;
    }

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getMediaData(ProductEntity $product): array
    {
        $media = [];
        $productMediaCollection = $product->getMedia();
        if ($productMediaCollection) {
            foreach ($productMediaCollection as $productMediaEntity) {
                $mediaEntity = $productMediaEntity->getMedia();
                if ($mediaEntity) {
                    $media[] = [
                        'id' => $productMediaEntity->getId(),
                        'mediaId' => $mediaEntity->getId(),
                        'fileName' => $mediaEntity->getFileName(),
                    ];
                }
            }
        }

        return $media;
    }

    /**
     * Cleans orphans
     */
    public function cleanOrphans(): void
    {
        if ($this->capturedProductMediaCollection) {
            $ids = [];
            foreach ($this->capturedProductMediaCollection->getElements() as $productMediaEntity) {
                $ids[] = ['id' => $productMediaEntity->getId()];
            }
            if ($ids) {
                $this->productMediaRepository->delete($ids, $this->getContext());
            }
        }
        $this->capturedProductMediaCollection = null;
    }
}
