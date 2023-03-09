<?php

namespace Payever\PayeverPayments\tests\unit\Controller;

use Payever\PayeverPayments\Controller\ProductsAndInventoryController;
use Payever\PayeverPayments\Service\Management\ExportManager;
use Payever\PayeverPayments\Service\Management\ImportManager;
use Payever\PayeverPayments\Service\Management\SubscriptionManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductsAndInventoryControllerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|ContainerInterface
     */
    private $container;

    /**
     * @var MockObject|SubscriptionManager
     */
    private $subscriptionManager;

    /**
     * @var MockObject|ExportManager
     */
    private $exportManager;

    /**
     * @var MockObject|ImportManager
     */
    private $importManager;

    /**
     * @var ProductsAndInventoryController
     */
    private $controller;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->subscriptionManager = $this->getMockBuilder(SubscriptionManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->exportManager = $this->getMockBuilder(ExportManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->importManager = $this->getMockBuilder(ImportManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->controller = new ProductsAndInventoryController(
            $this->subscriptionManager,
            $this->exportManager,
            $this->importManager
        );
        $this->controller->setContainer($this->container);
    }

    public function testToggleSubscriptionLegacy()
    {
        $this->subscriptionManager->expects($this->once())
            ->method('toggleSubscription')
            ->willReturn(true);
        $this->subscriptionManager->expects($this->once())
            ->method('getErrors')
            ->willReturn([]);
        $this->assertNotEmpty($this->controller->toggleSubscriptionLegacy());
    }

    public function testToggleSubscription()
    {
        $this->subscriptionManager->expects($this->once())
            ->method('toggleSubscription')
            ->willReturn(true);
        $this->subscriptionManager->expects($this->once())
            ->method('getErrors')
            ->willReturn([]);
        $this->assertNotEmpty($this->controller->toggleSubscription());
    }

    public function testExportLegacy()
    {
        $this->exportManager->expects($this->once())
            ->method('enqueueExport')
            ->willReturn(true);
        $this->exportManager->expects($this->once())
            ->method('getErrors')
            ->willReturn([]);
        $this->exportManager->expects($this->once())
            ->method('getBatchCount')
            ->willReturn(5);
        $this->assertNotEmpty($this->controller->exportLegacy());
    }

    public function testExport()
    {
        $this->exportManager->expects($this->once())
            ->method('enqueueExport')
            ->willReturn(true);
        $this->exportManager->expects($this->once())
            ->method('getErrors')
            ->willReturn([]);
        $this->exportManager->expects($this->once())
            ->method('getBatchCount')
            ->willReturn(5);
        $this->assertNotEmpty($this->controller->export());
    }

    public function testImport()
    {
        $this->importManager->expects($this->once())
            ->method('import')
            ->willReturn(true);
        $this->importManager->expects($this->once())
            ->method('getErrors')
            ->willReturn([]);
        $this->assertNotEmpty(
            $this->controller->import(
                $this->getMockBuilder(Request::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            )
        );
    }
}
