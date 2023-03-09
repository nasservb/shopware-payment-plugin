<?php

namespace Payever\PayeverPayments\tests\unit\Service\ActionHandler;

use Payever\ExternalIntegration\Products\Enum\ProductTypeEnum;
use Payever\ExternalIntegration\Products\Http\RequestEntity\ProductRequestEntity;
use Payever\ExternalIntegration\ThirdParty\Action\ActionPayload;
use Payever\ExternalIntegration\ThirdParty\Action\ActionResult;
use Payever\PayeverPayments\Service\ActionHandler\CreateProduct;
use Payever\PayeverPayments\Service\Transformer\ProductTransformer;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\Content\Product\ProductEntity;

class CreateProductTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|ProductTransformer
     */
    protected $transformer;

    /**
     * @var CreateProduct
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
        $this->handler = new CreateProduct($this->transformer);
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
                $requestEntity = $this->getMockBuilder(ProductRequestEntity::class)
                    ->disableOriginalConstructor()
                    ->onlyMethods(['getSku'])
                    ->addMethods(['getType'])
                    ->getMock()
            );
        $requestEntity->expects($this->any())
            ->method('getSku')
            ->willReturn('some-sku');
        $requestEntity->expects($this->any())
            ->method('getType')
            ->willReturn($type = ProductTypeEnum::TYPE_PHYSICAL);
        $this->transformer->expects($this->once())
            ->method('getType')
            ->willReturn($type);
        $this->transformer->expects($this->once())
            ->method('transformFromPayeverIntoShopwareProduct')
            ->willReturn(
                $this->getMockBuilder(ProductEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->transformer->expects($this->once())
            ->method('getProductData')
            ->willReturn(['some' => 'data']);
        $actionResult->expects($this->once())
            ->method('incrementCreated');
        $this->handler->handle($actionPayload, $actionResult);
    }
}
