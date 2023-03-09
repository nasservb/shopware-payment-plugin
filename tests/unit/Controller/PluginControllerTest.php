<?php

namespace Payever\PayeverPayments\tests\unit\Controller;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Payever\ExternalIntegration\Core\Http\Response;
use Payever\ExternalIntegration\Plugins\Base\PluginRegistryInfoProviderInterface;
use Payever\ExternalIntegration\Plugins\Http\ResponseEntity\PluginVersionResponseEntity;
use Payever\ExternalIntegration\Plugins\PluginsApiClient;
use Payever\PayeverPayments\Controller\PluginController;
use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class PluginControllerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|ContainerInterface
     */
    private $container;

    /**
     * @var MockObject|EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var MockObject|ClientFactory
     */
    private $clientFactory;

    /**
     * @var MockObject|SessionInterface
     */
    private $session;

    /**
     * @var MockObject|LoggerInterface|Logger
     */
    private $logger;

    /**
     * @var PluginController
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
        $this->eventDispatcher = $this->getMockBuilder(EventDispatcherInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->clientFactory = $this->getMockBuilder(ClientFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->session = $this->getMockBuilder(SessionInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->controller = new PluginController(
            $this->eventDispatcher,
            $this->clientFactory,
            $this->session,
            $this->logger
        );
        $this->controller->setContainer($this->container);
    }

    public function testGetNotifications()
    {
        $this->clientFactory->expects($this->once())
            ->method('getPluginsApiClient')
            ->willReturn(
                $pluginsApiClient = $this->getMockBuilder(PluginsApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $pluginsApiClient->expects($this->once())
            ->method('getLatestPluginVersion')
            ->willReturn(
                $response = $this->getMockBuilder(Response::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $response->expects($this->once())
            ->method('getResponseEntity')
            ->willReturn(
                $responseEntity = $this->getMockBuilder(PluginVersionResponseEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $responseEntity->expects($this->once())
            ->method('__call')
            ->willReturn('2.0.0');
        $pluginsApiClient->expects($this->once())
            ->method('getRegistryInfoProvider')
            ->willReturn(
                $pluginRegistryInfoProvider = $this->getMockBuilder(PluginRegistryInfoProviderInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $pluginRegistryInfoProvider->expects($this->once())
            ->method('getPluginVersion')
            ->willReturn('1.0.0');
        $this->assertNotEmpty($this->controller->getNotifications());
    }

    public function testGetNotificationsCaseException()
    {
        $this->clientFactory->expects($this->once())
            ->method('getPluginsApiClient')
            ->willThrowException(new \Exception());
        $this->logger->expects($this->once())
            ->method('notice');
        $this->assertNotEmpty($this->controller->getNotifications());
    }

    public function testDownloadLog()
    {
        $this->logger->expects($this->once())
            ->method('getHandlers')
            ->willReturn([
                $handler = $this->getMockBuilder(RotatingFileHandler::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $handler->expects($this->once())
            ->method('getUrl')
            ->willReturn(__FILE__);
        $this->eventDispatcher->expects($this->once())
            ->method('getListeners')
            ->willReturn([
                $this->getMockBuilder(\Symfony\Component\EventDispatcher\EventSubscriberInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            ]);
        $this->assertNotEmpty($this->controller->downloadLog());
    }

    public function testDownloadLogCaseNoHandlers()
    {
        $this->logger->expects($this->once())
            ->method('getHandlers')
            ->willReturn([]);
        $this->assertNotEmpty($this->controller->downloadLog());
    }
}
