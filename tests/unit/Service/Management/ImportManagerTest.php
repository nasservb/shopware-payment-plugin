<?php

namespace Payever\PayeverPayments\tests\unit\Service\Management;

use Payever\ExternalIntegration\ThirdParty\Enum\ActionEnum;
use Payever\PayeverPayments\Service\Helper\ConfigHelper;
use Payever\PayeverPayments\Service\Management\ImportManager;
use Payever\PayeverPayments\Service\Management\SubscriptionManager;
use Payever\PayeverPayments\Service\Management\SynchronizationManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;

class ImportManagerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|SubscriptionManager
     */
    private $subscriptionManager;

    /**
     * @var MockObject|SynchronizationManager
     */
    private $synchronizationManager;

    /**
     * @var MockObject|ConfigHelper
     */
    private $configHelper;

    /**
     * @var MockObject|LoggerInterface
     */
    private $logger;

    /**
     * @var ImportManager
     */
    private $manager;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->subscriptionManager = $this->getMockBuilder(SubscriptionManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->synchronizationManager = $this->getMockBuilder(SynchronizationManager::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->configHelper = $this->getMockBuilder(ConfigHelper::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->manager = new ImportManager(
            $this->subscriptionManager,
            $this->synchronizationManager,
            $this->configHelper,
            $this->logger
        );
    }

    public function testImport()
    {
        $this->configHelper->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        $this->subscriptionManager->expects($this->once())
            ->method('getSupportedActions')
            ->willReturn([
                $syncAction = ActionEnum::ACTION_CREATE_PRODUCT,
            ]);
        $this->configHelper->expects($this->once())
            ->method('getProductsSyncExternalId')
            ->willReturn($externalId = 'some-external-id');
        $this->synchronizationManager->expects($this->once())
            ->method('handleAction');
        $this->manager->import(
            $syncAction,
            $externalId,
            \json_encode(['some' => 'data'])
        );
    }

    public function testImportCaseInvalidPayload()
    {
        $this->configHelper->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        $this->subscriptionManager->expects($this->once())
            ->method('getSupportedActions')
            ->willReturn([
                $syncAction = ActionEnum::ACTION_CREATE_PRODUCT,
            ]);
        $this->configHelper->expects($this->once())
            ->method('getProductsSyncExternalId')
            ->willReturn($externalId = 'some-external-id');
        $this->synchronizationManager->expects($this->never())
            ->method('handleAction');
        $this->manager->import(
            $syncAction,
            $externalId,
            ''
        );
    }

    public function testImportCaseInvalidExternalId()
    {
        $this->configHelper->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        $this->subscriptionManager->expects($this->once())
            ->method('getSupportedActions')
            ->willReturn([
                $syncAction = ActionEnum::ACTION_CREATE_PRODUCT,
            ]);
        $this->configHelper->expects($this->once())
            ->method('getProductsSyncExternalId')
            ->willReturn('some-external-id');
        $this->synchronizationManager->expects($this->never())
            ->method('handleAction');
        $this->manager->import(
            $syncAction,
            'some-other-external-id',
            ''
        );
    }

    public function testImportCaseInvalidAction()
    {
        $this->configHelper->expects($this->once())
            ->method('isProductsSyncEnabled')
            ->willReturn(true);
        $this->subscriptionManager->expects($this->once())
            ->method('getSupportedActions')
            ->willReturn([
                ActionEnum::ACTION_CREATE_PRODUCT,
            ]);
        $this->synchronizationManager->expects($this->never())
            ->method('handleAction');
        $this->manager->import(
            'unknown-action',
            'some-other-external-id',
            ''
        );
    }
}
