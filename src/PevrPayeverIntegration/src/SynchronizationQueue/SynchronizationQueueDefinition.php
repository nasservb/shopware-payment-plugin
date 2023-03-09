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

namespace Payever\PayeverPayments\SynchronizationQueue;

use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class SynchronizationQueueDefinition extends \Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition
{
    public const ENTITY_NAME = 'payever_synchronization_queue';

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
        return SynchronizationQueueEntity::class;
    }

    /**
     * {@inheritDoc}
     */
    public function getCollectionClass(): string
    {
        return SynchronizationQueueCollection::class;
    }

    /**
     * {@inheritDoc}
     */
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField(
                SynchronizationQueueEntity::FIELD_ID,
                SynchronizationQueueEntity::FIELD_ID
            ))
                ->addFlags(new PrimaryKey(), new Required()),
            new StringField(
                SynchronizationQueueEntity::FIELD_ACTION,
                SynchronizationQueueEntity::FIELD_ACTION
            ),
            new StringField(
                SynchronizationQueueEntity::FIELD_DIRECTION,
                SynchronizationQueueEntity::FIELD_DIRECTION
            ),
            new BlobField(
                SynchronizationQueueEntity::FIELD_PAYLOAD,
                SynchronizationQueueEntity::FIELD_PAYLOAD
            ),
            new IntField(
                SynchronizationQueueEntity::FIELD_ATTEMPT,
                SynchronizationQueueEntity::FIELD_ATTEMPT
            )
        ]);
    }
}
