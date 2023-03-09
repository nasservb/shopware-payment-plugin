<?php

namespace Payever\Tests;

use Payever\Stub\BehatExtension\Context\PaymentContext as BaseContext;

class PaymentContext extends BaseContext
{
    /** @var ShopwarePluginConnectorLt64|ShopwarePluginConnector */
    protected $connector;

    /**
     * @Given /^(?:|I )configure stub product reference$/
     * @throws \Exception
     */
    public function configureStubProductReference()
    {
        $this->expectReferenceToBe($this->connector->getStubProductSku());
    }
}
