<?php
// phpcs:ignoreFile --

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

namespace Payever\PayeverPayments\Migration;

use Shopware\Core\Framework\Migration\MigrationStep;
use Doctrine\DBAL\Connection;

/**
 * @SuppressWarnings(PHPMD.LongClassName)
 */
class Migration1666934452PayeverOrderTotals extends MigrationStep
{
    /**
     * {@inheritDoc}
     */
    public function getCreationTimestamp(): int
    {
        return 1666934452;
    }

    /**
     * {@inheritDoc}
     */
    public function update(Connection $connection): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `payever_order_totals` (
  `id` binary(16) NOT NULL,
  `order_id` binary(16) NOT NULL,
  `captured_total` double DEFAULT 0 COMMENT 'Captured Amount',
  `cancelled_total` double DEFAULT 0 COMMENT 'Cancelled Amount',
  `refunded_total` double DEFAULT 0 COMMENT 'Refunded Amount',
  `created_at` datetime(3) NOT NULL COMMENT 'Creation time',
  `updated_at` datetime(3) DEFAULT NULL COMMENT 'Update time',
  `manual` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Manual action flag',
  PRIMARY KEY (`id`),
  KEY `fk.order_id` (`order_id`),
  CONSTRAINT `fk.order_id` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
)
ENGINE = InnoDB
DEFAULT CHARSET = utf8mb4
COLLATE = utf8mb4_unicode_ci
COMMENT 'Payever Totals';
SQL;
        $connection->executeUpdate($sql);
    }

    /**
     * {@inheritDoc}
     */
    public function updateDestructive(Connection $connection): void
    {
    }
}
