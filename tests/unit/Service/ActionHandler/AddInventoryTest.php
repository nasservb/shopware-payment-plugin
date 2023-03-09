<?php

namespace Payever\PayeverPayments\tests\unit\Service\ActionHandler;

use Payever\ExternalIntegration\Inventory\Http\MessageEntity\InventoryChangedEntity;
use Payever\ExternalIntegration\ThirdParty\Action\ActionPayload;
use Payever\ExternalIntegration\ThirdParty\Action\ActionResult;
use Payever\PayeverPayments\Service\ActionHandler\AddInventory;
use Payever\PayeverPayments\Service\Transformer\InventoryTransformer;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;

class AddInventoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|InventoryTransformer
     */
    protected $transformer;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var AddInventory
     */
    protected $handler;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->transformer = $this->getMockBuilder(InventoryTransformer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->handler = new AddInventory($this->transformer);
        $this->handler->setLogger($this->logger);
    }

    public function testGetSupportedAction()
    {
        $this->assertNotEmpty($this->handler->getSupportedAction());
    }

    public function testHandle()
    {
        /** @var MockObject|ActionPayload $actionPayload */
        $actionPayload = $this->getMockBuilder(ActionPayload::class)
            ->disableOriginalConstructor()
            ->getMock();
        /** @var MockObject|ActionResult $actionResult */
        $actionResult = $this->getMockBuilder(ActionResult::class)
            ->disableOriginalConstructor()
            ->getMock();
        $actionPayload->expects($this->once())
            ->method('getPayloadEntity')
            ->willReturn(
                $requestEntity = $this->getMockBuilder(InventoryChangedEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $requestEntity->expects($this->any())
            ->method('__call')
            ->willReturn('some-sku');
        $this->transformer->expects($this->once())
            ->method('getProduct')
            ->willReturn(
                $this->getMockBuilder(ProductEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->transformer->expects($this->once())
            ->method('updateStock');
        $actionResult->expects($this->once())
            ->method('incrementUpdated');
        $this->handler->handle($actionPayload, $actionResult);
    }
}
