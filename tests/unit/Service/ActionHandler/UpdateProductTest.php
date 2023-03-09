<?php

namespace Payever\PayeverPayments\tests\unit\Service\ActionHandler;

use Payever\ExternalIntegration\Products\Enum\ProductTypeEnum;
use Payever\ExternalIntegration\Products\Http\RequestEntity\ProductRequestEntity;
use Payever\ExternalIntegration\ThirdParty\Action\ActionPayload;
use Payever\ExternalIntegration\ThirdParty\Action\ActionResult;
use Payever\PayeverPayments\Service\ActionHandler\UpdateProduct;
use Payever\PayeverPayments\Service\Transformer\ProductTransformer;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductEntity;

class UpdateProductTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var MockObject|ProductTransformer
     */
    protected $transformer;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var UpdateProduct
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
        $this->logger = $this->getMockBuilder(LoggerInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->handler = new UpdateProduct($this->transformer);
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
            ->method('incrementUpdated');
        $this->handler->handle($actionPayload, $actionResult);
    }

    public function testHandleCaseNoSku()
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
                $this->getMockBuilder(ProductRequestEntity::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $actionResult->expects($this->once())
            ->method('incrementSkipped');
        $this->handler->handle($actionPayload, $actionResult);
    }

    public function testHandleCaseException()
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
        $actionResult->expects($this->once())
            ->method('incrementSkipped');
        $this->handler->handle($actionPayload, $actionResult);
    }

    public function testHandleCaseUnsupportedType()
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
        $this->transformer->expects($this->any())
            ->method('getType')
            ->willReturn(ProductTypeEnum::TYPE_PHYSICAL);
        $this->handler->handle($actionPayload, $actionResult);
    }

    public function testHandleCaseVariant()
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
                    ->onlyMethods(['getSku', 'isVariant'])
                    ->addMethods(['getType'])
                    ->getMock()
            );
        $requestEntity->expects($this->any())
            ->method('getSku')
            ->willReturn('some-sku');
        $requestEntity->expects($this->any())
            ->method('getType')
            ->willReturn($type = ProductTypeEnum::TYPE_PHYSICAL);
        $this->transformer->expects($this->any())
            ->method('getType')
            ->willReturn(ProductTypeEnum::TYPE_PHYSICAL);
        $requestEntity->expects($this->any())
            ->method('isVariant')
            ->willReturn(true);
        $this->handler->handle($actionPayload, $actionResult);
    }

    public function testAssertActionIsNotStalled()
    {
        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('assertActionIsNotStalled');
        $method->setAccessible(true);
        $product = $this->getMockBuilder(ProductEntity::class)
            ->disableOriginalConstructor()
            ->getMock();
        $requestEntity = $this->getMockBuilder(ProductRequestEntity::class)
            ->disableOriginalConstructor()
            ->addMethods(['getUpdatedAt'])
            ->getMock();
        $requestEntity->expects($this->once())
            ->method('getUpdatedAt')
            ->willReturn(new \DateTime('-1day'));
        $product->expects($this->once())
            ->method('getUpdatedAt')
            ->willReturn(new \DateTime());
        $this->expectException(\BadMethodCallException::class);
        $method->invoke($this->handler, $product, $requestEntity);
    }
}
