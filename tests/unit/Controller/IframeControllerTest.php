<?php

namespace Payever\PayeverPayments\tests\unit\Controller;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Payever\PayeverPayments\Controller\IframeController;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

class IframeControllerTest extends \PHPUnit\Framework\TestCase
{
    /** @var MockObject|ContainerInterface */
    private $container;

    /** @var IframeController */
    private $controller;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->container->expects($this->any())
            ->method('has')
            ->willReturn(true);
        $this->controller = new IframeController();
        $this->controller->setContainer($this->container);
    }

    public function testExecute()
    {
        $containerMocks = [
            'request_stack' => $requestStack = $this->getMockBuilder(RequestStack::class)
                ->disableOriginalConstructor()
                ->getMock(),
            TemplateFinder::class => $templateFinder = $this->getMockBuilder(TemplateFinder::class)
                ->disableOriginalConstructor()
                ->getMock(),
            'event_dispatcher' => $this->getMockBuilder(EventDispatcherInterface::class)
                ->disableOriginalConstructor()
                ->getMock(),
            'twig' => $this->getMockBuilder(Environment::class)
                ->disableOriginalConstructor()
                ->getMock(),
            SeoUrlPlaceholderHandlerInterface::class => $seoUrlReplacer = $this->getMockBuilder(SeoUrlPlaceholderHandlerInterface::class)
                ->disableOriginalConstructor()
                ->getMock(),
            SystemConfigService::class => $systemConfigService = $this->getMockBuilder(SystemConfigService::class)
                                                                      ->disableOriginalConstructor()
                                                                      ->getMock(),
        ];
        $this->container->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) use ($containerMocks) {
                return $containerMocks[$key] ?? null;
            });

        $requestMocks = [
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $this->getMockBuilder(SalesChannelContext::class)
                ->disableOriginalConstructor()
                ->getMock(),
            RequestTransformer::STOREFRONT_URL => 'some.domain',
        ];
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->query = $this->getMockBuilder(ParameterBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->attributes = $this->getMockBuilder(ParameterBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $request->attributes->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) use ($requestMocks) {
                return $requestMocks[$key] ?? null;
            });
        $requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);
        $templateFinder->expects($this->any())
            ->method('find')
            ->willReturn('some-view');
        $seoUrlReplacer->expects($this->once())
            ->method('replace')
            ->willReturn('some-content');
        $this->controller->execute($request);
    }
}
