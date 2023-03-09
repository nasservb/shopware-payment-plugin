<?php

namespace Payever\PayeverPayments\tests\unit\Service\Helper;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\ResultStatement;
use Payever\PayeverPayments\Service\Helper\ConnectionHelper;
use PHPUnit\Framework\MockObject\MockObject;

class ConnectionHelperTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|Connection
     */
    private $connection;

    /**
     * @var ConnectionHelper
     */
    private $helper;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->helper = new ConnectionHelper($this->connection);
    }

    public function testExecuteQuery()
    {
        $this->connection->expects($this->once())
            ->method('executeQuery');
        $this->connection->executeQuery('');
    }

    public function testFetchOne()
    {
        $className = ResultStatement::class;
        $method = 'fetchColumn';
        if (interface_exists('Doctrine\DBAL\Driver\Result')) {
            $className = Result::class;
            $method = 'fetchOne';
        }
        /** @var MockObject|Result|ResultStatement $statement */
        $statement = $this->getMockBuilder($className)
            ->disableOriginalConstructor()
            ->getMock();
        $statement->expects($this->once())
            ->method($method);
        $this->helper->fetchOne($statement);
    }

    public function testFetchAssociative()
    {
        $method = 'fetchAssoc';
        if (method_exists($this->connection, 'fetchAssociative')) {
            $method = 'fetchAssociative';
        }
        $this->connection->expects($this->once())
            ->method($method);
        $this->helper->fetchAssociative('');
    }

    public function testFetchAllAssociative()
    {
        $className = ResultStatement::class;
        $method = 'fetchAll';
        if (interface_exists('Doctrine\DBAL\Driver\Result')) {
            $className = Result::class;
            $method = 'fetchAllAssociative';
        }
        /** @var MockObject|Result|ResultStatement $statement */
        $statement = $this->getMockBuilder($className)
            ->disableOriginalConstructor()
            ->getMock();
        $statement->expects($this->once())
            ->method($method)
            ->willReturn([]);
        $this->helper->fetchAllAssociative($statement);
    }
}
