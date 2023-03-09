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

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\ResultStatement;
use Doctrine\DBAL\FetchMode;

class ConnectionHelper
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * @param string $sql
     * @param array $params
     * @return Result|ResultStatement
     * @throws \Doctrine\DBAL\Exception
     */
    public function executeQuery($sql, array $params = [])
    {
        return $this->connection->executeQuery($sql, $params);
    }

    /**
     * @param string $sql
     * @param array $params
     * @return int|string
     * @throws \Doctrine\DBAL\Exception
     */
    public function executeStatement($sql, array $params = [])
    {
        if (method_exists($this->connection, 'executeStatement')) {
            return $this->connection->executeStatement($sql, $params);
        }

        return $this->connection->executeUpdate($sql, $params);
    }

    /**
     * @param Result|ResultStatement $statement
     * @return mixed
     */
    public function fetchOne($statement)
    {
        $method = method_exists($statement, 'fetchOne') ? 'fetchOne' : 'fetchColumn';

        return call_user_func([$statement, $method]);
    }

    /**
     * @param string $sql
     * @return mixed
     */
    public function fetchAssociative($sql, array $params = [])
    {
        $method = method_exists($this->connection, 'fetchAssociative') ? 'fetchAssociative' : 'fetchAssoc';

        return call_user_func_array([$this->connection, $method], [$sql, $params]);
    }

    /**
     * @param ResultStatement|Result $statement
     * @return array
     */
    public function fetchAllAssociative($statement): array
    {
        $result = [];
        $method = 'fetchAll';
        $params = [FetchMode::ASSOCIATIVE];
        if (method_exists($statement, 'fetchAllAssociative')) {
            $method = 'fetchAllAssociative';
            $params = [];
        }
        $callResult = call_user_func_array([$statement, $method], $params);
        if (is_array($callResult)) {
            $result = $callResult;
        }

        return $result;
    }
}
