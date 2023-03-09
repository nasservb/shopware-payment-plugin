<?php

namespace Payever\PayeverPayments\tests\unit\Controller;

use Payever\ExternalIntegration\Payments\Notification\NotificationResult;
use Payever\PayeverPayments\Controller\FinanceExpressController;
use Payever\PayeverPayments\Service\Payment\FinanceExpress\FailureHandler;
use Payever\PayeverPayments\Service\Payment\FinanceExpress\SuccessHandler;
use Payever\PayeverPayments\Service\Payment\Notification\NotificationRequestProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FinanceExpressControllerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|ContainerInterface
     */
    private $container;

    /**
     * @var SuccessHandler
     */
    private $successHandler;

    /**
     * @var FailureHandler
     */
    private $failureHandler;

    /**
     * @var NotificationRequestProcessor
     */
    private $notificationRequestProcessor;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var FinanceExpressController
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
        $this->successHandler = $this->getMockBuilder(SuccessHandler::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->failureHandler = $this->getMockBuilder(FailureHandler::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->notificationRequestProcessor = $this->getMockBuilder(NotificationRequestProcessor::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->controller = new FinanceExpressController(
            $this->successHandler,
            $this->failureHandler,
            $this->notificationRequestProcessor,
            $this->logger
        );
        $this->controller->setContainer($this->container);
    }

    public function testSuccess()
    {
        $this->container->expects($this->once())
            ->method('get')
            ->willReturn(
                $router = $this->getMockBuilder(RouterInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $router->expects($this->once())
            ->method('generate')
            ->willReturn('http://some.domain/path');
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query = $request->query = $this->getMockBuilder(ParameterBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|SalesChannelContext $context */
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query->expects($this->once())
            ->method('get')
            ->willReturn('some-payment-uuid');
        $this->successHandler->expects($this->once())
            ->method('handle')
            ->willReturn('some-order-hex');
        $this->assertNotEmpty($this->controller->success($request, $context));
    }

    public function testSuccessCaseException()
    {
        $translator = $this->getMockBuilder(TranslatorInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->container->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) use ($translator) {
                if ('translator' === $key) {
                    return $translator;
                }
            });
        $translator->expects($this->once())
            ->method('trans')
            ->willReturn('some-translation');
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query = $request->query = $this->getMockBuilder(ParameterBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $headers = $request->headers = $this->getMockBuilder(HeaderBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $headers->expects($this->once())
            ->method('get')
            ->willReturn('iframe');
        /** @var MockObject|SalesChannelContext $context */
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query->expects($this->any())
            ->method('get')
            ->willReturn('some-payment-uuid');
        $this->successHandler->expects($this->once())
            ->method('handle')
            ->willThrowException(new \Exception());
        $this->assertNotEmpty($this->controller->success($request, $context));
    }

    public function testPendingCaseException()
    {
        $this->container->expects($this->any())
            ->method('has')
            ->willReturn(true);
        $containerMocks = [
            'translator' => $translator = $this->getMockBuilder(TranslatorInterface::class)
                ->disableOriginalConstructor()
                ->getMock(),
            'session' => $session = $this->getMockBuilder(Session::class)
                ->disableOriginalConstructor()
                ->getMock(),
            'router' => $router = $this->getMockBuilder(RouterInterface::class)
                ->disableOriginalConstructor()
                ->getMock(),
            'request_stack' => $requestStack = $this->getMockBuilder(Request::class)
                                                    ->disableOriginalConstructor()
                                                    ->getMock(),
        ];
        $this->container->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) use ($containerMocks) {
                return $containerMocks[$key] ?? null;
            });

        $requestStack->expects($this->any())
                     ->method('getSession')
                     ->willReturn($session);

        $translator->expects($this->any())
            ->method('trans')
            ->willReturn('some-translation');
        $session->expects($this->once())
            ->method('getFlashBag')
            ->willReturn(
                $this->getMockBuilder(FlashBagInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $router->expects($this->once())
            ->method('generate')
            ->willReturn('http://some.domain/path');
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query = $request->query = $this->getMockBuilder(ParameterBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $headers = $request->headers = $this->getMockBuilder(HeaderBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $headers->expects($this->once())
            ->method('get')
            ->willReturn('document');
        /** @var MockObject|SalesChannelContext $context */
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query->expects($this->any())
            ->method('get')
            ->willReturn('some-payment-uuid');
        $this->successHandler->expects($this->once())
            ->method('handle')
            ->willThrowException(new \Exception());
        $this->assertNotEmpty($this->controller->pending($request, $context));
    }

    public function testCancel()
    {
        $translator = $this->getMockBuilder(TranslatorInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->container->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) use ($translator) {
                if ('translator' === $key) {
                    return $translator;
                }
            });
        $translator->expects($this->once())
            ->method('trans')
            ->willReturn('some-translation');
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query = $request->query = $this->getMockBuilder(ParameterBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $headers = $request->headers = $this->getMockBuilder(HeaderBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $headers->expects($this->once())
            ->method('get')
            ->willReturn('iframe');
        /** @var MockObject|SalesChannelContext $context */
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query->expects($this->any())
            ->method('get')
            ->willReturn('some-payment-uuid');
        $this->assertNotEmpty($this->controller->cancel($request, $context));
    }

    public function testFailure()
    {
        $translator = $this->getMockBuilder(TranslatorInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->container->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) use ($translator) {
                if ('translator' === $key) {
                    return $translator;
                }
            });
        $translator->expects($this->once())
            ->method('trans')
            ->willReturn('some-translation');
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query = $request->query = $this->getMockBuilder(ParameterBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $headers = $request->headers = $this->getMockBuilder(HeaderBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $headers->expects($this->once())
            ->method('get')
            ->willReturn('iframe');
        /** @var MockObject|SalesChannelContext $context */
        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query->expects($this->any())
            ->method('get')
            ->willReturn('some-payment-uuid');
        $this->assertNotEmpty($this->controller->failure($request, $context));
    }

    public function testNotice()
    {
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query = $request->query = $this->getMockBuilder(ParameterBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query->expects($this->any())
            ->method('get')
            ->willReturn('some-payment-uuid');
        $this->notificationRequestProcessor->expects($this->once())
            ->method('processNotification')
            ->willReturn(
                $notificationResult = $this->getMockBuilder(NotificationResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $notificationResult->expects($this->once())
            ->method('isFailed')
            ->willReturn(false);
        $this->assertNotEmpty($this->controller->notice($request));
    }

    public function testNoticeCaseException()
    {
        /** @var MockObject|Request $request */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query = $request->query = $this->getMockBuilder(ParameterBag::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query->expects($this->any())
            ->method('get')
            ->willReturn('some-payment-uuid');
        $this->notificationRequestProcessor->expects($this->once())
            ->method('processNotification')
            ->willThrowException(new \Exception());
        $this->assertNotEmpty($this->controller->notice($request));
    }

    public function testHandleFailureCaseRedirectToSeoPath()
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('handleFailure');
        $method->setAccessible(true);

        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $session = $this->getMockBuilder(Session::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->container->expects($this->any())
            ->method('has')
            ->willReturn(true);

        $containerMocks = [
            'translator' => $translator = $this->getMockBuilder(TranslatorInterface::class)
                                               ->disableOriginalConstructor()
                                               ->getMock(),
            'session' => $session = $this->getMockBuilder(Session::class)
                                         ->disableOriginalConstructor()
                                         ->getMock(),
            'router' => $router = $this->getMockBuilder(RouterInterface::class)
                                       ->disableOriginalConstructor()
                                       ->getMock(),
            'request_stack' => $requestStack = $this->getMockBuilder(Request::class)
                                                    ->disableOriginalConstructor()
                                                    ->getMock(),
        ];

        $requestStack->expects($this->any())
                     ->method('getSession')
                     ->willReturn($session);

        $this->container->expects($this->any())
                        ->method('get')
                        ->willReturnCallback(function ($key) use ($containerMocks) {
                            return $containerMocks[$key] ?? null;
                        });
        $session->expects($this->once())
            ->method('getFlashBag')
            ->willReturn(
                $this->getMockBuilder(FlashBagInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->failureHandler->expects($this->once())
            ->method('getSeoPath')
            ->willReturn('/some/path');

        $method->invoke($this->controller, $context, 'some-payment-uuid', 'some-message');
    }

    public function testHandleFailureCaseRedirectToSeoPathCaseException()
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('handleFailure');
        $method->setAccessible(true);

        $context = $this->getMockBuilder(SalesChannelContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $containerMocks = [
            'translator' => $translator = $this->getMockBuilder(TranslatorInterface::class)
                ->disableOriginalConstructor()
                ->getMock(),
            'session' => $session = $this->getMockBuilder(Session::class)
                ->disableOriginalConstructor()
                ->getMock(),
            'router' => $router = $this->getMockBuilder(RouterInterface::class)
                ->disableOriginalConstructor()
                ->getMock(),
            'request_stack' => $requestStack = $this->getMockBuilder(Request::class)
                                                    ->disableOriginalConstructor()
                                                    ->getMock(),
        ];
        $requestStack->expects($this->any())
                     ->method('getSession')
                     ->willReturn($session);
        $this->container->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) use ($containerMocks) {
                return $containerMocks[$key] ?? null;
            });
        $this->container->expects($this->any())
            ->method('has')
            ->willReturn(true);
        $this->container->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) use ($session) {
                return 'session' === $key ? $session : null;
            });
        $session->expects($this->once())
            ->method('getFlashBag')
            ->willReturn(
                $this->getMockBuilder(FlashBagInterface::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->failureHandler->expects($this->once())
            ->method('getSeoPath')
            ->willThrowException(new \Exception());
        $router->expects($this->once())
            ->method('generate')
            ->willReturn('http://some.domain/path');

        $method->invoke($this->controller, $context, 'some-payment-uuid', 'some-message');
    }
}
