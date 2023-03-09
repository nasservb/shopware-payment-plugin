<?php

namespace Payever\PayeverPayments\tests\unit\Service\Helper;

use Payever\PayeverPayments\Service\Helper\SalesChannelContextHelper;
use PHPUnit\Framework\MockObject\MockObject;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class SalesChannelContextHelperTest extends \PHPUnit\Framework\TestCase
{
    /** @var MockObject|RequestStack */
    private $requestStack;

    /** @var SalesChannelContextHelper */
    private $helper;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->requestStack = $this->getMockBuilder(RequestStack::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->helper = new SalesChannelContextHelper($this->requestStack);
    }

    public function testGetRequest()
    {
        $this->requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(
                $this->getMockBuilder(Request::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->assertNotEmpty($this->helper->getRequest());
    }

    public function testGetRequestCaseException()
    {
        $this->expectException(\LogicException::class);
        $this->helper->getRequest();
    }

    public function testGetSalesChannelContext()
    {
        $this->requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(
                $request = $this->getMockBuilder(Request::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $request->expects($this->once())
            ->method('get')
            ->willReturn(
                $this->getMockBuilder(SalesChannelContext::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->assertNotEmpty($this->helper->getSalesChannelContext());
    }

    public function testGetSalesChannelContextCaseException()
    {
        $this->requestStack->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn(
                $request = $this->getMockBuilder(Request::class)
                    ->disableOriginalConstructor()
                    ->getMock()
            );
        $this->expectException(\LogicException::class);
        $this->helper->getSalesChannelContext();
    }
}
