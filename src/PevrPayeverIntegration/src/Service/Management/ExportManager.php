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

use Payever\PayeverPayments\Messenger\ExportProducer;
use Payever\PayeverPayments\Service\Helper\ConfigHelper;
use Payever\PayeverPayments\Service\Iterator\InventoryIterator;
use Payever\PayeverPayments\Service\Iterator\ProductsIterator;
use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use Payever\PayeverPayments\Service\Transformer\ProductAwareTrait;
use Payever\PayeverPayments\Service\Transformer\ProductTransformer;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class ExportManager
{
    use GenericManagerTrait;
    use ProductAwareTrait;

    private const DEFAULT_LIMIT = 5;

    /**
     * @var EntityRepositoryInterface
     */
    private $entityRepository;

    /**
     * @var ClientFactory
     */
    private $clientFactory;

    /**
     * @var ProductTransformer
     */
    private $productTransformer;

    /**
     * @var ExportProducer
     */
    private $exportProducer;

    /**
     * @var SubscriptionManager
     */
    private $subscriptionManager;

    /**
     * @var int
     */
    private $batchCount = 0;

    /**
     * @param EntityRepositoryInterface $entityRepository
     * @param ClientFactory $clientFactory
     * @param ProductTransformer $productTransformer
     * @param ExportProducer $exportBatchProducer
     * @param SubscriptionManager $subscriptionManager
     * @param ConfigHelper $configHelper
     * @param LoggerInterface $logger
     */
    public function __construct(
        EntityRepositoryInterface $entityRepository,
        ClientFactory $clientFactory,
        ProductTransformer $productTransformer,
        ExportProducer $exportBatchProducer,
        SubscriptionManager $subscriptionManager,
        ConfigHelper $configHelper,
        LoggerInterface $logger
    ) {
        $this->entityRepository = $entityRepository;
        $this->clientFactory = $clientFactory;
        $this->productTransformer = $productTransformer;
        $this->exportProducer = $exportBatchProducer;
        $this->subscriptionManager = $subscriptionManager;
        $this->configHelper = $configHelper;
        $this->logger = $logger;
    }

    /**
     * @return int
     */
    public function getBatchCount(): int
    {
        return $this->batchCount;
    }

    /**
     * @return bool
     */
    public function enqueueExport(): bool
    {
        $result = false;
        $this->cleanMessages();
        try {
            if ($this->isProductsSyncEnabled()) {
                if ($this->isProductsOutwardSyncEnabled()) {
                    $total = $this->getExportCollectionSize();
                    $page = 0;
                    $pageSize = self::DEFAULT_LIMIT;
                    $this->batchCount = $pages = (int) ceil($total / $pageSize);
                    $this->logger->debug(sprintf(
                        'To be produced total items %s in %s batches each with size %s',
                        $total,
                        $pages,
                        $pageSize
                    ));
                    while ($page < $pages) {
                        $this->exportProducer->produce($pageSize, $page * $pageSize);
                        $page++;
                    }
                }
            }
            $result = true;
        } catch (\Exception $e) {
            $this->errors[] = $e->getMessage();
        }
        $this->logMessages();

        return $result;
    }

    /**
     * @return int
     */
    private function getExportCollectionSize(): int
    {
        return $this->entityRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('parentId', null)),
            $this->getContext()
        )->getTotal();
    }

    /**
     * @param int $limit
     * @param int $offset
     * @return int
     */
    public function processBatch(int $limit, int $offset): int
    {
        try {
            $aggregate = 0;
            $productList = $this->entityRepository->search(
                $this->getBaseProductCriteria()
                    ->setLimit($limit)
                    ->setOffset($offset)
                    ->addFilter(new EqualsFilter('parentId', null))
                    ->addFilter(new EqualsFilter('active', true)),
                $this->getContext()
            )
                ->getEntities()
                ->getElements();
            $externalId = $this->configHelper->getProductsSyncExternalId();
            $aggregate = $this->clientFactory->getProductsApiClient()
                ->exportProducts(
                    new ProductsIterator($this->productTransformer, $productList),
                    $externalId
                );
            $this->clientFactory->getInventoryApiClient()
                ->exportInventory(new InventoryIterator($productList), $externalId);
        } catch (\Exception $exception) {
            $this->subscriptionManager->disable();
            $this->logger->warning($exception->getMessage());
        }

        return $aggregate;
    }
}
