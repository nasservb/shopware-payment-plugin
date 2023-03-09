<?php

namespace Payever\PayeverPayments\tests\unit\Service\Management;

use Payever\ExternalIntegration\Core\Http\Response;
use Payever\ExternalIntegration\Core\PseudoRandomStringGenerator;
use Payever\ExternalIntegration\ThirdParty\Http\ResponseEntity\SubscriptionResponseEntity;
use Payever\ExternalIntegration\ThirdParty\ThirdPartyApiClient;
use Payever\PayeverPayments\Service\Management\SubscriptionManager;
use Payever\PayeverPayments\Service\Management\SynchronizationQueueManager;
use Payever\PayeverPayments\Service\PayeverApi\ClientFactory;
use Payever\PayeverPayments\Service\Setting\PayeverSettingGeneralStruct;
use Payever\PayeverPayments\Service\Setting\SettingsService;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\RouterInterface;

class SubscriptionManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|RouterInterface
     */
    private $router;

    /**
     * @var MockObject|PseudoRandomStringGenerator
     */
    private $pseudoRandomStringGenerator;

    /**
     * @var MockObject|SettingsService
     */
    private $settingsService;

    /**
     * @var MockObject|ClientFactory
     */
    private $clientFactory;

    /**
     * @var MockObject|SynchronizationQueueManager
     */
    private $synchronizationQueueManager;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var SubscriptionManager
     */
    private $manager;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->router = $this->getMockBuilder(RouterInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->pseudoRandomStringGenerator = $this->getMockBuilder(PseudoRandomStringGenerator::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->settingsService = $this->getMockBuilder(SettingsService::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->clientFactory = $this->getMockBuilder(ClientFactory::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->synchronizationQueueManager = $this->getMockBuilder(SynchronizationQueueManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->manager = new SubscriptionManager(
            $this->router,
            $this->pseudoRandomStringGenerator,
            $this->settingsService,
            $this->clientFactory,
            $this->synchronizationQueueManager,
            $this->logger
        );
    }

    public function testToggleSubscription()
    {
        $this->settingsService->expects($this->any())
            ->method('getSettings')
            ->willReturn(
                $configCarrier = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $configCarrier->expects($this->any())
            ->method('isProductsSyncEnabled')
            ->willReturn(false);
        $this->pseudoRandomStringGenerator->expects($this->once())
            ->method('generate')
            ->willReturn('some-external-id');
        $this->router->expects($this->any())
            ->method('generate')
            ->willReturn('http://some.domain/path');
        $this->clientFactory->expects($this->once())
            ->method('getThirdPartyApiClient')
            ->willReturn(
                $thirdPartyApiClient = $this->getMockBuilder(ThirdPartyApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $thirdPartyApiClient->expects($this->once())
            ->method('subscribe');
        $thirdPartyApiClient->expects($this->once())
            ->method('getSubscriptionStatus')
            ->willReturn(
                $subscriptionRecordResponse = $this->getMockBuilder(Response::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $subscriptionRecordResponse->expects($this->once())
            ->method('getResponseEntity')
            ->willReturn(
                $this->getMockBuilder(SubscriptionResponseEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->assertTrue($this->manager->toggleSubscription());
    }

    public function testToggleSubscriptionCaseSubscribeException()
    {
        $this->settingsService->expects($this->any())
            ->method('getSettings')
            ->willReturn(
                $configCarrier = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $configCarrier->expects($this->any())
            ->method('isProductsSyncEnabled')
            ->willReturn(false);
        $this->clientFactory->expects($this->once())
            ->method('getThirdPartyApiClient')
            ->willReturn(
                $thirdPartyApiClient = $this->getMockBuilder(ThirdPartyApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $thirdPartyApiClient->expects($this->once())
            ->method('subscribe')
            ->willThrowException(new \Exception());
        $this->assertFalse($this->manager->toggleSubscription());
    }

    public function testToggleSubscriptionCaseUnsubscribe()
    {
        $this->settingsService->expects($this->any())
            ->method('getSettings')
            ->willReturn(
                $configCarrier = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $configCarrier->expects($this->any())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        $configCarrier->expects($this->once())
            ->method('getProductsSyncExternalId')
            ->willReturn('some-external-id');
        $this->router->expects($this->any())
            ->method('generate')
            ->willReturn('http://some.domain/path');
        $this->clientFactory->expects($this->once())
            ->method('getThirdPartyApiClient')
            ->willReturn(
                $thirdPartyApiClient = $this->getMockBuilder(ThirdPartyApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $thirdPartyApiClient->expects($this->once())
            ->method('unsubscribe');
        $this->assertFalse($this->manager->toggleSubscription());
    }

    public function testToggleSubscriptionCaseUnsubscribeCaseException()
    {
        $this->settingsService->expects($this->any())
            ->method('getSettings')
            ->willReturn(
                $configCarrier = $this->getMockBuilder(PayeverSettingGeneralStruct::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $configCarrier->expects($this->any())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        $configCarrier->expects($this->once())
            ->method('getProductsSyncExternalId')
            ->willReturn('some-external-id');
        $this->router->expects($this->any())
            ->method('generate')
            ->willReturn('http://some.domain/path');
        $this->clientFactory->expects($this->once())
            ->method('getThirdPartyApiClient')
            ->willReturn(
                $thirdPartyApiClient = $this->getMockBuilder(ThirdPartyApiClient::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $thirdPartyApiClient->expects($this->once())
            ->method('unsubscribe')
            ->willThrowException(new \Exception());
        $this->logger->expects($this->once())
            ->method('warning');
        $this->assertFalse($this->manager->toggleSubscription());
    }
}
