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

namespace Payever\PayeverPayments\OrderItems;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OrderItemsDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'payever_order_items';

    /**
     * {@inheritDoc}
     */
    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    /**
     * {@inheritDoc}
     */
    public function getEntityClass(): string
    {
        return OrderItemsEntity::class;
    }

    /**
     * {@inheritDoc}
     */
    public function getCollectionClass(): string
    {
        return OrderItemsCollection::class;
    }

    /**
     * {@inheritDoc}
     */
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField(
                OrderItemsEntity::FIELD_ID,
                OrderItemsEntity::FIELD_ID
            ))
                ->addFlags(new PrimaryKey(), new Required()),
            (new FkField(
                'order_id',
                OrderItemsEntity::FIELD_ORDER_ID,
                OrderDefinition::class
            ))
                ->addFlags(new Required()),
            new StringField(
                'item_type',
                OrderItemsEntity::FIELD_ITEM_TYPE
            ),
            new BlobField(
                'item_id',
                OrderItemsEntity::FIELD_ITEM_ID
            ),
            new StringField(
                'identifier',
                OrderItemsEntity::FIELD_IDENTIFIER
            ),
            new StringField(
                'label',
                OrderItemsEntity::FIELD_LABEL
            ),
            new IntField(
                'quantity',
                OrderItemsEntity::FIELD_QUANTITY
            ),
            new FloatField(
                'unit_price',
                OrderItemsEntity::FIELD_UNIT_PRICE
            ),
            new FloatField(
                'total_price',
                OrderItemsEntity::FIELD_TOTAL_PRICE
            ),
            new IntField(
                'qty_captured',
                OrderItemsEntity::FIELD_QTY_CAPTURED
            ),
            new IntField(
                'qty_cancelled',
                OrderItemsEntity::FIELD_QTY_CANCELLED
            ),
            new IntField(
                'qty_refunded',
                OrderItemsEntity::FIELD_QTY_REFUNDED
            ),
            (new ManyToOneAssociationField(
                'order',
                'order_id',
                OrderDefinition::class,
                'id',
                false
            ))
        ]);
    }
}
