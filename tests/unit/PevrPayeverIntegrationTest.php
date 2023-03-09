<?php

namespace Payever\PayeverPayments\tests\unit;

use Payever\ExternalIntegration\Plugins\PluginsApiClient;
use Payever\PayeverPayments\PevrPayeverIntegration;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PevrPayeverIntegrationTest extends \PHPUnit\Framework\TestCase
{
    /** @var MockObject|ContainerInterface */
    private $container;

    /** @var PevrPayeverIntegration */
    private $plugin;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->plugin = new PevrPayeverIntegration(false, __DIR__);
        $this->plugin->setContainer(
            $this->container = $this->getMockBuilder(ContainerInterface::class)
                ->disableOriginalConstructor()
                ->getMock()
        );
    }

    public function testActivate()
    {
        $context = $this->getMockBuilder(ActivateContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $context->expects($this->once())
            ->method('getContext')
            ->willReturn(
                $this->getMockBuilder(Context::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->container->expects($this->any())
            ->method('has')
            ->willReturn(true);
        $containerMocks = [
            'custom_field.repository' => $this->getMockBuilder(EntityRepositoryInterface::class)
                ->disableOriginalConstructor()
                ->getMock(),
            PluginsApiClient::class => $this->getMockBuilder(PluginsApiClient::class)
                ->disableOriginalConstructor()
                ->getMock(),
        ];
        $this->container->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) use ($containerMocks) {
                return $containerMocks[$key] ?? null;
            });
        $this->plugin->activate($context);
    }

    public function testDeactivate()
    {
        $context = $this->getMockBuilder(DeactivateContext::class)
            ->disableOriginalConstructor()
            ->getMock();
        $context->expects($this->once())
            ->method('getContext')
            ->willReturn(
                $this->getMockBuilder(Context::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->container->expects($this->any())
            ->method('has')
            ->willReturn(true);
        $containerMocks = [
            'custom_field.repository' => $customFieldRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
                ->disableOriginalConstructor()
                ->getMock(),
            PluginsApiClient::class => $this->getMockBuilder(PluginsApiClient::class)
                ->disableOriginalConstructor()
                ->getMock(),
        ];
        $this->container->expects($this->any())
            ->method('get')
            ->willReturnCallback(function ($key) use ($containerMocks) {
                return $containerMocks[$key] ?? null;
            });
        $customFieldRepository->expects($this->any())
            ->method('searchIds')
            ->willReturn(
                $searchResult = $this->getMockBuilder(IdSearchResult::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $searchResult->expects($this->any())
            ->method('firstId')
            ->willReturn('1');
        $this->plugin->deactivate($context);
    }

    public function testRegisterPluginCaseException()
    {
        $this->container->expects($this->once())
            ->method('has')
            ->willThrowException(new \Exception());
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('registerPlugin');
        $method->setAccessible(true);
        $method->invoke($this->plugin);
    }

    public function testUnregisterPluginCaseException()
    {
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('unregisterPlugin');
        $method->setAccessible(true);

        $this->container->expects($this->once())
            ->method('has')
            ->willThrowException(new \Exception());

        $method->invoke($this->plugin);
    }

    public function testDeactivateOrderTransactionCustomField()
    {
        $reflection = new \ReflectionClass($this->plugin);
        $method = $reflection->getMethod('deactivateOrderTransactionCustomField');
        $method->setAccessible(true);

        $context = $this->getMockBuilder(Context::class)
            ->disableOriginalConstructor()
            ->getMock();
        $customFieldRepository = $this->getMockBuilder(EntityRepositoryInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->container->expects($this->once())
            ->method('get')
            ->willReturn($customFieldRepository);
        $customFieldRepository->expects($this->never())
            ->method('delete');

        $method->invoke($this->plugin, $context);
    }
}
