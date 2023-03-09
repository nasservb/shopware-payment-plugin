<?php

namespace Payever\PayeverPayments\tests\unit\Controller;

use Payever\PayeverPayments\Controller\PluginCommandController;
use Payever\PayeverPayments\ScheduledTask\ExecutePluginCommandsTaskHandler;
use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use Payever\PayeverPayments\Service\Setting\SettingsServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Store\Exception\StoreSignatureValidationException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\UriSigner;

class PluginCommandControllerTest extends \PHPUnit\Framework\TestCase
{
    /** @var MockObject|ContainerInterface */
    private $container;

    /** @var MockObject|ExecutePluginCommandsTaskHandler */
    private $executePluginCommandsTaskHandler;

    /** @var MockObject|UriSigner */
    private $uriSigner;

    /** @var SettingsServiceInterface */
    private $settingsService;

    /** @var ClientFactory */
    private $apiClientFactory;

    /** @var PluginCommandController */
    private $controller;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->settingsService = $this->getMockBuilder(SettingsServiceInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->apiClientFactory = $this->getMockBuilder(ClientFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->executePluginCommandsTaskHandler = $this->getMockBuilder(ExecutePluginCommandsTaskHandler::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->uriSigner = $this->getMockBuilder(UriSigner::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->controller = new PluginCommandController(
            $this->settingsService,
            $this->apiClientFactory,
            $this->executePluginCommandsTaskHandler,
            $this->uriSigner
        );
        $this->controller->setContainer($this->container);
    }

    public function testExecute()
    {
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->expects($this->once())
            ->method('getRequestUri')
            ->willReturn('/some/uri');
        $this->uriSigner->expects($this->once())
            ->method('check')
            ->willReturn(true);
        $this->controller->execute($request);
    }

    public function testExecuteCaseSignatureNotValid()
    {
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->expects($this->once())
            ->method('getRequestUri')
            ->willReturn('/some/uri');
        $this->uriSigner->expects($this->once())
            ->method('check')
            ->willReturn(false);
        $this->expectException(StoreSignatureValidationException::class);
        $this->controller->execute($request);
    }
}
