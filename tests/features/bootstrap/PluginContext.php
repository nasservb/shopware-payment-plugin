<?php

namespace Payever\Tests;

use Assert\Assertion;
use Assert\AssertionFailedException;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;

class PluginContext extends \Payever\Stub\BehatExtension\Context\PluginContext
{
    /** @var ShopwarePluginConnector|ShopwarePluginConnectorLt64 */
    protected $connector;

    /** @var FrontendContext */
    private $frontend;

    /**
     * {@inheritDoc}
     */
    public function beforeScenario(BeforeScenarioScope $scope = null)
    {
        parent::beforeScenario();
        if ($scope) {
            $this->frontend = $scope->getEnvironment()->getContext(FrontendContext::class);
        }
    }

    /**
     * @BeforeScenario @setupPaymentMethods
     */
    public function setupPaymentMethods(): void
    {
        if ($this->connector instanceof ShopwarePluginConnectorLt64) {
            $this->connector->setupPaymentMethods();
        }
        $this->connector->connectPaymentMethodsToSalesChannel();
    }

    /**
     * {@inheritDoc}
     */
    public function visitProductPage($sku)
    {
        $this->frontend->visitPath($this->connector->getProductUrl($sku));
    }

    /**
     * @Given /^the product variant with SKU "([^"]+)" must have the following option values:$/
     *
     * @param $sku
     * @param TableNode $table
     *
     * @throws AssertionFailedException
     */
    public function assertProductVariantFieldValues($sku, TableNode $table)
    {
        foreach ($table->getRowsHash() as $field => $value) {
            $this->assertProductVariantOptionValue($sku, $field, $value);
        }
    }

    /**
     * @Given /^the product variant with SKU "([^"]+)" option "([^"]+)" value must be equal to "([^"]*)$/
     *
     * @param string $sku
     * @param string $field
     * @param string $value
     *
     * @throws AssertionFailedException
     */
    public function assertProductVariantOptionValue($sku, $field, $value = '')
    {
        Assertion::true(
            $this->connector->getProductVariantOptionValueExists($sku, $field, $value),
            sprintf('Product variant with sku "%s" does not have value "%s" for option "%s"', $sku, $value, $field)
        );
    }
}
