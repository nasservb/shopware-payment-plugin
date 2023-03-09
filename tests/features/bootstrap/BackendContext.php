<?php

namespace Payever\Tests;

use Assert\Assertion;
use Assert\AssertionFailedException;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Mink;
use Behat\MinkExtension\Context\MinkAwareContext;
use Payever\Stub\BehatExtension\Context\PluginAwareContext;
use Payever\Stub\BehatExtension\ServiceContainer\PluginConnectorInterface;

class BackendContext implements PluginAwareContext, MinkAwareContext
{
    /** @var Mink */
    private $mink;

    /** @var array */
    private $minkConfig;

    /** @var ShopwarePluginConnector|ShopwarePluginConnectorLt64 */
    private $connector;

    /** @var array */
    private $extensionConfig;

    /** @var FrontendContext */
    private $frontend;

    /**
     * {@inheritDoc}
     */
    public function setPluginConnector(PluginConnectorInterface $connector)
    {
        $this->connector = $connector;
    }

    /**
     * {@inheritDoc}
     */
    public function setExtensionConfig(array $config)
    {
        $this->extensionConfig = $config;
    }

    /**
     * {@inheritDoc}
     */
    public function setMink(Mink $mink)
    {
        $this->mink = $mink;
    }

    /**
     * {@inheritDoc}
     */
    public function setMinkParameters(array $parameters)
    {
        $this->minkConfig = $parameters;
    }

    /**
     * @BeforeScenario
     */
    public function beforeScenario(BeforeScenarioScope $scope): void
    {
        $this->frontend = $scope->getEnvironment()->getContext(FrontendContext::class);
    }

    /**
     * @Given /^(?:|I )open plugin configuration page$/
     *
     * @throws \Exception
     */
    public function openPluginConfigPage(): void
    {
        $path = sprintf('/admin#/sw/extension/config/%s', ShopwarePluginConnector::PLUGIN_CODE);
        if ($this->connector instanceof ShopwarePluginConnectorLt64) {
            $path = sprintf('/admin#/sw/plugin/settings/%s', ShopwarePluginConnectorLt64::PLUGIN_CODE);
        }
        $this->frontend->visitPath($path);
        $this->frontend->waitTillTextAppears('Enable Sandbox');
    }

    /**
     * @Given /^(?:|I )am on stub product page$/
     */
    public function openStubProductPage(): void
    {
        $this->frontend->visitPath($this->connector->getProductUrl(null));
    }

    /**
     * @Given /^(?:|I )open product form page$/
     *
     * @throws \Behat\Mink\Exception\DriverException
     * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
     */
    public function openProductFormPage(): void
    {
        $this->visitProductFormPage('/admin#/sw/product/create/base');
    }

    /**
     * @Given /^(?:|I )open order grid page$/
     *
     * @throws \Behat\Mink\Exception\DriverException
     * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
     */
    public function openOrderGridPage(): void
    {
        $this->visitProductFormPage('/admin#/sw/order/index');
    }

    /**
     * @Given /^(?:|I )am on product with SKU "([^"]+)" form page$/
     *
     * @param string $sku
     * @throws AssertionFailedException
     * @throws \Behat\Mink\Exception\DriverException
     * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
     * @throws \Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException
     */
    public function iAmOnProductWithSkuFormPage($sku): void
    {
        $product = $this->connector->getProductBySku($sku);
        Assertion::notNull($product, sprintf('Product with sku "%s" not found', $sku));
        $this->visitProductFormPage(sprintf('/admin#/sw/product/detail/%s/base', bin2hex($product->getId())));
    }

    /**
     * @param string $path
     * @throws \Behat\Mink\Exception\DriverException
     * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
     */
    private function visitProductFormPage(string $path): void
    {
        $this->frontend->visitPath($path);
        $driver = $this->mink->getSession()->getDriver();
        $productFormLoadedText = 'New product';
        $selector = '.smart-bar__content .smart-bar__header h2';
        $condition = "document.querySelectorAll('$selector').length > 0";
        $this->mink->getSession()->wait(3000, $condition);
        $elementExists = $driver->evaluateScript($condition);
        if ($elementExists) {
            $elementTextContent = trim($driver->evaluateScript(
                "document.querySelectorAll('$selector')[0].textContent"
            ));
            if ($productFormLoadedText === $elementTextContent) {
                $fieldsToWaitSelectorList = [
                    'input[name="sw-field--product-name"]',
                    'input[name="sw-field--product-productNumber"]',
                    'input[name="sw-price-field-gross"]',
                    'input[name="sw-price-field-net"]',
                    'input[name="sw-field--product-stock"]',
                ];
                foreach ($fieldsToWaitSelectorList as $selector) {
                    $condition = "document.querySelectorAll('$selector').length > 0";
                    $this->mink->getSession()->wait(5000, $condition);
                }
            }
        }
        $this->mink->getSession()->wait(5000);
    }

    /**
     * @Given /^(?:|I )am on product grid page$/
     */
    public function openProductGridPage(): void
    {
        $this->frontend->visitPath('/admin#/sw/product/index');
        $driver = $this->mink->getSession()->getDriver();
        $productFormLoadedText = 'Products';
        $selector = '.smart-bar__content .smart-bar__header h2';
        $condition = "document.querySelectorAll('$selector').length > 0";
        $this->mink->getSession()->wait(3000, $condition);
        $elementExists = $driver->evaluateScript($condition);
        if ($elementExists) {
            $elementTextContent = trim($driver->evaluateScript(
                "document.querySelectorAll('$selector')[0].textContent"
            ));
            if (false !== strpos($elementTextContent, $productFormLoadedText)) {
                $selector = '.sw-data-grid__actions-menu .sw-context-button__button';
                $condition = "document.querySelectorAll('$selector').length > 0";
                $this->mink->getSession()->wait(5000, $condition);
            }
        }
    }

    /**
     * @Given /^(?:|I )connect payment methods to sales channel$/
     */
    public function connectPaymentMethodsToSalesChannel(): void
    {
        $this->connector->connectPaymentMethodsToSalesChannel();
    }

    /**
     * @Given /^(?:|I )clear cache$/
     */
    public function clearCache(): void
    {
        $this->connector->clearCache();
    }

    /**
     * @Given /^payment method "([^"]+)" with variant "([^"]+)" must exist$/
     *
     * @param string $methodCode
     * @param string $variantId
     *
     * @return array found method
     *
     * @throws AssertionFailedException
     * @throws \RuntimeException
     */
    public function assertPaymentMethodExistsWithVariant(string $methodCode, string $variantId): array
    {
        $methods = $this->connector->findPaymentMethodsByCode($methodCode);
        Assertion::notEmpty($methods, sprintf('Can not find methods with code %s', $methodCode));

        foreach ($methods as $method) {
            $customFields = \json_decode($method['custom_fields'], true);

            if ($customFields['payever_method_code'] === $methodCode
                && $customFields['payever_variant_id'] === $variantId
            ) {
                return $method;
            }
        }

        throw new \RuntimeException(
            sprintf('Payment method %s with variant id %s not found', $methodCode, $variantId)
        );
    }

    /**
     * @Given the following payment methods must exist:
     *
     * @param TableNode $table
     *
     * @throws AssertionFailedException
     * @throws \RuntimeException
     */
    public function assertPaymentMethodsExistTable(TableNode $table): void
    {
        foreach ($table as $row) {
            if (!empty($row['variant_id'])) {
                $method = $this->assertPaymentMethodExistsWithVariant($row['method_code'], $row['variant_id']);

                if (!empty($row['active'])) {
                    Assertion::eq(
                        (bool) $method['active'],
                        $row['active'] === 'true',
                        "{$row['method_code']}: %s"
                    );
                }
            } else {
                $this->assertPaymentMethodExists($row['method_code']);
            }
        }
    }

    /**
     * @Given /^payment method "([^"]+)" must exist$/
     *
     * @param string $methodCode
     *
     * @throws AssertionFailedException
     */
    public function assertPaymentMethodExists(string $methodCode): void
    {
        $methods = $this->connector->findPaymentMethodsByCode($methodCode);
        Assertion::notEmpty($methods, sprintf('Can not find methods with code %s', $methods));
    }

    /**
     * @Given /^new (order|transaction) state must be "(open|authorized|paid|refunded|returned|returned_partially|shipped|refunded_partially|paid_partially|cancelled|failed|completed|in_process|in_progress)"$/
     *
     * @param string $type
     * @param string $expectedState
     * @throws AssertionFailedException
     */
    public function assertOrderOrTransactionState(string $type, string $expectedState): void
    {
        $newOrder = $this->connector->getLastOrderInfo();

        Assertion::eq($newOrder["{$type}_state"], $expectedState);
    }

    /**
     * @Then /^(?:|I )wait sync queue (may not\s)?exists and populated with size (\d+)$/
     * @param bool $not
     * @param int $size
     */
    public function waitSyncQueueExistsAndPopulatedWithSize($not = false, $size = 1): void
    {
        $sizeMatched = false;
        $attempt = 60;
        while ($attempt) {
            if ($this->connector->getSyncQueueCount() >= $size) {
                $sizeMatched = true;
                break;
            }
            sleep(1);
            $attempt--;
        }
        if (!$not) {
            if (!$sizeMatched) {
                echo 'Sync queue to be populated awaiting is timed out';
            }
        }
    }

    /**
     * @Then /^(?:|I )wait payment methods count is (\d+)$/
     *
     * @param int $size
     * @throws AssertionFailedException
     */
    public function waitPaymentMethodsCountIs($size = 1)
    {
        $sizeMatched = false;
        $count = 0;
        $attempt = 60;
        while ($attempt) {
            $count = $this->connector->getPaymentMethodsCount();
            if ($count >= $size) {
                $sizeMatched = true;
                break;
            }
            sleep(1);
            $attempt--;
        }
        if (!$sizeMatched) {
            echo "Payment methods to be populated awaiting is timed out. Total found: $count" . PHP_EOL;
        }
    }

    /**
     * @Then /^(?:|I )clear products sync queue$/
     */
    public function clearSynchronizationQueue(): void
    {
        $this->connector->clearSynchronizationQueue();
        if ($this->connector instanceof ShopwarePluginConnector) {
            $this->connector->clearEnqueue();
        }
    }

    /**
     * @Then /^(?:|I )run products sync cronjob$/
     */
    public function runProductsSyncCronJob(): void
    {
        $this->connector->runSynchronizationQueueTaskHandler();
    }

    /**
     * @Then /^(?:|I )run message consumer$/
     */
    public function runMessageConsumer(): void
    {
        $this->connector->runMessageConsumer(12, 30);
    }

    /**
     * @Then /^(?:|I )select order status "([^"]+)"$/
     *
     * @param string $status
     * @throws \Behat\Mink\Exception\DriverException
     * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
     */
    public function selectOrderStatus($status)
    {
        $selectId = 'order_status_select';
        $js = <<<JS
(function () {
    let elements = document.querySelectorAll('select[name=sw-field--selectedActionName]');
    let orderStatusSelect = elements[2];
    if (!orderStatusSelect) {
        throw new Error('Order status select not found');
    }
    orderStatusSelect.id='$selectId';
})()
JS;
        $this->mink->getSession()->getDriver()->executeScript($js);
        $this->frontend->selectOption($selectId, $status);
    }

    /**
     * @Then /^(?:|I )select delivery status "([^"]+)"$/
     *
     * @param string $status
     * @throws \Behat\Mink\Exception\DriverException
     * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
     */
    public function selectDeliveryStatus($status)
    {
        $selectId = 'delivery_status_select';
        $js = <<<JS
(function () {
    let elements = document.querySelectorAll('select[name=sw-field--selectedActionName]');
    let orderStatusSelect = elements[1];
    if (!orderStatusSelect) {
        throw new Error('Delivery status select not found');
    }
    orderStatusSelect.id='$selectId';
})()
JS;
        $this->mink->getSession()->getDriver()->executeScript($js);
        $this->frontend->selectOption($selectId, $status);
    }

    /**
     * @Then /^(?:|I )fill refund amount$/
     *
     * @throws \Behat\Mink\Exception\DriverException
     * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
     */
    public function fillRefundAmount()
    {
        $js = <<<JS
(function () {
    let amountEl = document.querySelector('input[name=sw-field--input-refund]');
    amountEl.value=100;
    let amountElEvent = new Event('input');
    amountEl.dispatchEvent(amountElEvent);
})()
JS;
        $this->mink->getSession()->getDriver()->executeScript($js);
    }

    /**
     * @Then /^(?:|I )set order totals "([^"]+)" to "([^"]+)"$/
     *
     * @param string $status
     * @throws \Behat\Mink\Exception\DriverException
     * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
     */
    public function addTotals($field, $value)
    {
        $this->connector->addTotals($field, $value);
    }
}
