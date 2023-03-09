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
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation\PropertyGroupOptionTranslationEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupTranslation\PropertyGroupTranslationEntity;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class OptionManager
{
    use \Payever\PayeverPayments\Service\DataAbstractionLayer\GenericTrait;

    /**
     * @var EntityRepositoryInterface
     */
    private $propertyGroupRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $propertyGroupTranslationRepo;

    /**
     * @var EntityRepositoryInterface
     */
    private $propertyGroupOptionRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $propertyGroupOptionTransRepo;

    /**
     * @var EntityRepositoryInterface
     */
    private $configuratorRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $productOptionRepository;

    /**
     * @var ProductConfiguratorSettingCollection|null
     */
    private $capturedConfiguratorSettings;

    /**
     * @var array
     */
    private $capturedOptionIds = [];

    /**
     * @param EntityRepositoryInterface $propertyGroupRepository
     * @param EntityRepositoryInterface $propertyGroupTranslationRepo
     * @param EntityRepositoryInterface $propertyGroupOptionRepository
     * @param EntityRepositoryInterface $propertyGroupOptionTransRepo
     * @param EntityRepositoryInterface $configuratorRepository
     * @param EntityRepositoryInterface $productOptionRepository
     */
    public function __construct(
        EntityRepositoryInterface $propertyGroupRepository,
        EntityRepositoryInterface $propertyGroupTranslationRepo,
        EntityRepositoryInterface $propertyGroupOptionRepository,
        EntityRepositoryInterface $propertyGroupOptionTransRepo,
        EntityRepositoryInterface $configuratorRepository,
        EntityRepositoryInterface $productOptionRepository
    ) {
        $this->propertyGroupRepository = $propertyGroupRepository;
        $this->propertyGroupTranslationRepo = $propertyGroupTranslationRepo;
        $this->propertyGroupOptionRepository = $propertyGroupOptionRepository;
        $this->propertyGroupOptionTransRepo = $propertyGroupOptionTransRepo;
        $this->configuratorRepository = $configuratorRepository;
        $this->productOptionRepository = $productOptionRepository;
    }

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getOptions(ProductEntity $product): array
    {
        $options = [];
        $propertyGroupOptionCollection = $product->getOptions();
        if ($propertyGroupOptionCollection) {
            foreach ($propertyGroupOptionCollection->getElements() as $propertyGroupOptionEntity) {
                $group = $propertyGroupOptionEntity->getGroup();
                $name = $group ? $group->getName() : null;
                $value = $propertyGroupOptionEntity->getName();
                if ($group && $name && $value) {
                    $options[] = [
                        'name' => $name,
                        'value' => $value,
                    ];
                }
            }
        }

        return $options;
    }

    /**
     * @param ProductEntity $product
     */
    public function captureOrphans(ProductEntity $product): void
    {
        $productConfigurationSettings = $product->getConfiguratorSettings();
        $this->capturedConfiguratorSettings = $productConfigurationSettings
            ? clone $productConfigurationSettings
            : null;
        $this->capturedOptionIds = [];
        $childrenCollection = $product->getChildren();
        if ($childrenCollection) {
            foreach ($childrenCollection->getElements() as $child) {
                $this->capturedOptionIds[$child->getId()] = $child->getOptionIds();
            }
        }
    }

    /**
     * @param ProductRequestEntity $requestEntity
     * @param ProductEntity $product
     * @return PropertyGroupOptionCollection
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function getPreparedOptionCollection(
        ProductRequestEntity $requestEntity,
        ProductEntity $product
    ): PropertyGroupOptionCollection {
        $parentProduct = $product->getParent();
        if (!$parentProduct) {
            throw new \BadMethodCallException('Parent product is not set to variant');
        }
        $propertyGroupOptionCollection = $product->getOptions();
        if (!$propertyGroupOptionCollection) {
            $propertyGroupOptionCollection = new PropertyGroupOptionCollection();
        }
        $orphanPropertyGroupOptions = clone $propertyGroupOptionCollection;
        $options = $requestEntity->getOptions();
        if ($options) {
            foreach ($options as $option) {
                $optionName = $option->getName();
                $optionValue = $option->getValue();
                if ($optionName && $optionValue) {
                    $optionNameFound = $optionValueFound = false;
                    foreach ($propertyGroupOptionCollection as $key => $propertyGroupOptionEntity) {
                        $propertyGroupEntity = $propertyGroupOptionEntity->getGroup();
                        $optionNameFound = $propertyGroupEntity &&
                            mb_strtolower($propertyGroupEntity->getName()) === mb_strtolower($optionName);
                        $optionValueFound = mb_strtolower($propertyGroupOptionEntity->getName())
                            === mb_strtolower($optionValue);
                        if ($optionNameFound && $optionValueFound) {
                            $orphanPropertyGroupOptions->remove($key);
                            break;
                        }
                    }
                    if ($optionNameFound && isset($propertyGroupEntity) && !$optionValueFound) {
                        $propertyGroupOption = $this->getPropertyGroupOptionEntity(
                            $parentProduct,
                            $propertyGroupEntity->getId(),
                            $optionValue
                        );
                        $propertyGroupOptionCollection->add($propertyGroupOption);
                    } elseif (!$optionNameFound && !$optionValueFound) {
                        $propertyGroup = $this->getPropertyGroupByName($optionName);
                        if (!$propertyGroup) {
                            $propertyGroup = new PropertyGroupEntity();
                            $propertyGroup->assign(
                                $propertyGroupData = [
                                    'id' => $this->getRandomHex(),
                                    'name' => $optionName,
                                ]
                            );
                            $this->propertyGroupRepository->upsert([$propertyGroupData], $this->getContext());
                            $propertyGroupTranslation = new PropertyGroupTranslationEntity();
                            $propertyGroupTranslation->assign(
                                $propertyGroupTranslationData = [
                                    'propertyGroupId' => $propertyGroup->getId(),
                                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                                    'name' => $optionName,
                                ]
                            );
                            $propertyGroupTranslation->setUniqueIdentifier($this->getRandomHex());
                            $propertyGroupTranslation->setPropertyGroup($propertyGroup);
                            $this->propertyGroupTranslationRepo->upsert(
                                [$propertyGroupTranslationData],
                                $this->getContext()
                            );
                        }
                        $propertyGroupOption = $this->getPropertyGroupOptionEntity(
                            $parentProduct,
                            $propertyGroup->getId(),
                            $optionValue
                        );
                        $propertyGroupOptionCollection->add($propertyGroupOption);
                    }
                }
            }
        }
        $configuratorSettings = $parentProduct->getConfiguratorSettings();
        foreach ($orphanPropertyGroupOptions as $key => $orphanPropertyGroupOption) {
            $propertyGroupOptionCollection->remove($key);
            if ($configuratorSettings) {
                $configuratorSettings->filterAndReduceByProperty(
                    'optionId',
                    $orphanPropertyGroupOption->getId()
                );
            }
        }

        return $propertyGroupOptionCollection;
    }

    /**
     * @param string $optionName
     * @return PropertyGroupEntity|null
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    public function getPropertyGroupByName(string $optionName): ?PropertyGroupEntity
    {
        /** @var PropertyGroupTranslationEntity|null $propertyGroupTranslation */
        $propertyGroupTranslation = $this->propertyGroupTranslationRepo->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('name', $optionName))
                ->addFilter(new EqualsFilter('languageId', Defaults::LANGUAGE_SYSTEM))
                ->addAssociation('propertyGroup'),
            $this->getContext()
        )
            ->getEntities()
            ->first();

        return $propertyGroupTranslation ? $propertyGroupTranslation->getPropertyGroup() : null;
    }

    /**
     * @param string $optionValueName
     * @return PropertyGroupOptionEntity|null
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    public function getPropertyGroupOptionByName(string $optionValueName): ?PropertyGroupOptionEntity
    {
        /** @var PropertyGroupOptionTranslationEntity|null $propertyOptionGroupTranslation */
        $propertyOptionGroupTranslation = $this->propertyGroupOptionTransRepo->search(
            (new Criteria())
                ->addFilter(new EqualsFilter('name', $optionValueName))
                ->addFilter(new EqualsFilter('languageId', Defaults::LANGUAGE_SYSTEM))
                ->addAssociation('propertyGroupOption'),
            $this->getContext()
        )
            ->getEntities()
            ->first();

        return $propertyOptionGroupTranslation ? $propertyOptionGroupTranslation->getPropertyGroupOption() : null;
    }

    /**
     * @param ProductEntity $parentProduct
     * @param string $propertyGroupEntityId
     * @param string $optionValue
     * @return PropertyGroupOptionEntity
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    private function getPropertyGroupOptionEntity(
        ProductEntity $parentProduct,
        string $propertyGroupEntityId,
        string $optionValue
    ): PropertyGroupOptionEntity {
        $propertyGroupOption = $this->getPropertyGroupOptionByName($optionValue);
        if (!$propertyGroupOption) {
            $propertyGroupOption = new PropertyGroupOptionEntity();
            $propertyGroupOption->assign(
                $propertyGroupOptionData = [
                    'id' => $this->getRandomHex(),
                    'groupId' => $propertyGroupEntityId,
                    'name' => $optionValue,
                ]
            );
            $this->propertyGroupOptionRepository->upsert([$propertyGroupOptionData], $this->getContext());
            $propertyOptionGroupTranslation = new PropertyGroupOptionTranslationEntity();
            $propertyOptionGroupTranslation->assign(
                $propertyOptionGroupTranslationData = [
                    'propertyGroupOptionId' => $propertyGroupOption->getId(),
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'name' => $optionValue,
                ]
            );
            $propertyOptionGroupTranslation->setUniqueIdentifier($this->getRandomHex());
            $this->propertyGroupOptionTransRepo->upsert(
                [$propertyOptionGroupTranslationData],
                $this->getContext()
            );
        }
        $configuratorSettings = $parentProduct->getConfiguratorSettings();
        if (!$configuratorSettings) {
            $configuratorSettings = new ProductConfiguratorSettingCollection();
        }
        $configurationFound = false;
        foreach ($configuratorSettings as $configEntity) {
            if (
                $configEntity->getProductId() === $parentProduct->getId()
                && $configEntity->getOptionId() === $propertyGroupOption->getId()
            ) {
                $configurationFound = true;
                break;
            }
        }
        if (!$configurationFound) {
            $productConfiguratorSettings = new ProductConfiguratorSettingEntity();
            $productConfiguratorSettings->assign([
                'id' => $this->getRandomHex(),
                'productId' => $parentProduct->getId(),
                'optionId' => $propertyGroupOption->getId(),
            ]);
            $propertyGroupOption->setProductConfiguratorSettings($configuratorSettings);
            $configuratorSettings->add($productConfiguratorSettings);
        }
        $parentProduct->setConfiguratorSettings($configuratorSettings);

        return $propertyGroupOption;
    }

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getOptionsData(ProductEntity $product): array
    {
        $data = [];
        $propertyGroupOptionCollection = $product->getOptions();
        if ($propertyGroupOptionCollection) {
            foreach ($propertyGroupOptionCollection->getElements() as $propertyGroupOption) {
                $data[] = [
                    'id' => $propertyGroupOption->getId(),
                    'groupId' => $propertyGroupOption->getGroupId(),
                    'name' => $propertyGroupOption->getName(),
                ];
            }
        }

        return $data;
    }

    /**
     * @param ProductEntity $product
     * @return array
     */
    public function getProductConfiguratorSettingData(ProductEntity $product): array
    {
        return $this->getConfiguratorSettingsData($product->getId(), $product->getConfiguratorSettings());
    }

    /**
     * @param string $productId
     * @param ProductConfiguratorSettingCollection|null $configuratorSettings
     * @return array
     */
    private function getConfiguratorSettingsData(
        string $productId,
        ProductConfiguratorSettingCollection $configuratorSettings = null
    ): array {
        $data = [];
        if ($configuratorSettings) {
            foreach ($configuratorSettings->getElements() as $productConfiguratorSetting) {
                $data[] = [
                    'id' => $productConfiguratorSetting->getId(),
                    'productId' => $productId,
                    'optionId' => $productConfiguratorSetting->getOptionId(),
                ];
            }
        }

        return $data;
    }

    /**
     * @param ProductEntity $product
     */
    public function cleanOrphans(ProductEntity $product): void
    {
        $ids = [];
        $data = $this->getConfiguratorSettingsData($product->getId(), $this->capturedConfiguratorSettings);
        foreach ($data as $row) {
            $ids[] = [
                'id' => $row['id'],
                'productId' => $row['productId'],
            ];
        }
        if ($ids) {
            $this->configuratorRepository->delete($ids, $this->getContext());
        }
        $optionIds = [];
        foreach ($this->capturedOptionIds as $productId => $ids) {
            if ($ids) {
                foreach ($ids as $optionId) {
                    $optionIds[] = [
                        'productId' => $productId,
                        'optionId' => $optionId,
                    ];
                }
            }
        }
        if ($optionIds) {
            $this->productOptionRepository->delete($optionIds, $this->getContext());
        }
    }
}
