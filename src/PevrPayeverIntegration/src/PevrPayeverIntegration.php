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

namespace Payever\PayeverPayments;

// phpcs:disable PSR1.Files.SideEffects
require_once __DIR__ . '/../vendor/autoload.php';
// phpcs:enable PSR1.Files.SideEffects

use Payever\ExternalIntegration\Plugins\PluginsApiClient;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class PevrPayeverIntegration extends Plugin
{
    public const PLUGIN_NAME = 'PevrPayeverIntegration';

    public const CUSTOM_FIELD_TRANSACTION_ID = 'payever_transaction_id';
    public const CUSTOM_FIELD_TRANSACTION_AMOUNT = 'payever_transaction_amount';
    public const CUSTOM_FIELD_PAN_ID = 'payever_pan_id';
    public const CUSTOM_FIELD_NOTIFICATION_TIMESTAMP = 'payever_notification_timestamp';
    public const CUSTOM_FIELD_ACCEPT_FEE = 'payever_accept_fee';
    public const CUSTOM_FIELD_FIXED_FEE = 'payever_fixed_fee';
    public const CUSTOM_FIELD_VARIABLE_FEE = 'payever_variable_fee';
    public const CUSTOM_FIELD_METHOD_CODE = 'payever_method_code';
    public const CUSTOM_FIELD_VARIANT_ID = 'payever_variant_id';
    public const CUSTOM_FIELD_IS_REDIRECT_METHOD = 'payever_is_redirect_method';

    /**
     * @var array[]
     */
    private $customFields = [
        [
            'name' => self::CUSTOM_FIELD_METHOD_CODE,
            'type' => CustomFieldTypes::TEXT,
        ],
        [
            'name' => self::CUSTOM_FIELD_VARIANT_ID,
            'type' => CustomFieldTypes::TEXT,
        ],
        [
            'name' => self::CUSTOM_FIELD_TRANSACTION_ID,
            'type' => CustomFieldTypes::TEXT,
        ],
        [
            'name' => self::CUSTOM_FIELD_PAN_ID,
            'type' => CustomFieldTypes::TEXT,
        ],
        [
            'name' => self::CUSTOM_FIELD_NOTIFICATION_TIMESTAMP,
            'type' => CustomFieldTypes::INT,
        ],
        [
            'name' => self::CUSTOM_FIELD_ACCEPT_FEE,
            'type' => CustomFieldTypes::BOOL,
        ],
        [
            'name' => self::CUSTOM_FIELD_FIXED_FEE,
            'type' => CustomFieldTypes::FLOAT,
        ],
        [
            'name' => self::CUSTOM_FIELD_VARIABLE_FEE,
            'type' => CustomFieldTypes::FLOAT,
        ],
        [
            'name' => self::CUSTOM_FIELD_TRANSACTION_AMOUNT,
            'type' => CustomFieldTypes::FLOAT,
        ],
        [
            'name' => self::CUSTOM_FIELD_IS_REDIRECT_METHOD,
            'type' => CustomFieldTypes::BOOL,
        ],
    ];

    /**
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context): void
    {
        $shopwareContext = $context->getContext();
        $this->activateOrderTransactionCustomField($shopwareContext);
        $this->registerPlugin();

        parent::activate($context);
    }

    /**
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context): void
    {
        $shopwareContext = $context->getContext();
        $this->deactivateOrderTransactionCustomField($shopwareContext);
        $this->unregisterPlugin();

        parent::deactivate($context);
    }

    /**
     * Registers plugin
     */
    private function registerPlugin(): void
    {
        try {
            if ($this->container->has(PluginsApiClient::class)) {
                /** @var PluginsApiClient $pluginsApiClient */
                $pluginsApiClient = $this->container->get(PluginsApiClient::class);
                $pluginsApiClient->registerPlugin();
            }
        } catch (\Exception $exception) {
            // silent
        }
    }

    /**
     * Unregisters plugin
     */
    private function unregisterPlugin(): void
    {
        try {
            if ($this->container->has(PluginsApiClient::class)) {
                /** @var PluginsApiClient $pluginsApiClient */
                $pluginsApiClient = $this->container->get(PluginsApiClient::class);
                $pluginsApiClient->unregisterPlugin();
            }
        } catch (\Exception $exception) {
            // silent
        }
    }

    /**
     * @param Context $context
     */
    private function activateOrderTransactionCustomField(Context $context): void
    {
        /** @var EntityRepositoryInterface $customFieldRepository */
        $customFieldRepository = $this->container->get('custom_field.repository');
        $customFieldRepository->upsert($this->customFields, $context);
    }

    /**
     * @param Context $context
     */
    private function deactivateOrderTransactionCustomField(Context $context): void
    {
        /** @var EntityRepositoryInterface $customFieldRepository */
        $customFieldRepository = $this->container->get('custom_field.repository');

        $ids = [];
        foreach ($this->customFields as $customField) {
            $customFieldId = $this->getCustomFieldId($customField['name'], $customFieldRepository, $context);
            if ($customFieldId->firstId()) {
                $ids[] = ['id' => $customFieldId->firstId()];
            }
        }

        if (!count($ids)) {
            return;
        }

        $customFieldRepository->delete($ids, $context);
    }

    /**
     * @param string $customFieldName
     * @param EntityRepositoryInterface $customFieldRepository
     * @param Context $context
     * @return IdSearchResult
     */
    private function getCustomFieldId(
        string $customFieldName,
        EntityRepositoryInterface $customFieldRepository,
        Context $context
    ): IdSearchResult {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $customFieldName));

        return $customFieldRepository->searchIds($criteria, $context);
    }
}
