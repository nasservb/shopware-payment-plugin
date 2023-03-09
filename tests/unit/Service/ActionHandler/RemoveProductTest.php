<?php

namespace Payever\PayeverPayments\tests\unit\Service\ActionHandler;

use Payever\ExternalIntegration\Products\Http\RequestEntity\ProductRemovedRequestEntity;
use Payever\ExternalIntegration\ThirdParty\Action\ActionPayload;
use Payever\ExternalIntegration\ThirdParty\Action\ActionResult;
use Payever\PayeverPayments\Service\ActionHandler\RemoveProduct;
use Payever\PayeverPayments\Service\Transformer\ProductTransformer;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Content\Product\ProductEntity;

class RemoveProductTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|ProductTransformer
     */
    protected $transformer;

    /**
     * @var RemoveProduct
     */
    protected $handler;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->transformer = $this->getMockBuilder(ProductTransformer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->handler = new RemoveProduct($this->transformer);
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
                $requestEntity = $this->getMockBuilder(ProductRemovedRequestEntity::class)
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
            ->method('remove');
        $actionResult->expects($this->once())
            ->method('incrementDeleted');
        $this->handler->handle($actionPayload, $actionResult);
    }
}
