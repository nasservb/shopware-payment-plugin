<?php

namespace Payever\Tests;

use Assert\Assertion;
use Assert\AssertionFailedException;
use Payever\Stub\BehatExtension\Context\FrontendContext as BaseFrontendContext;
use Payever\Stub\BehatExtension\Context\PluginAwareContext;
use Payever\Stub\BehatExtension\ServiceContainer\BackendCredentialsAwareInterface;
use Payever\Stub\BehatExtension\ServiceContainer\PluginConnectorInterface;

class FrontendContext extends BaseFrontendContext implements BackendCredentialsAwareInterface, PluginAwareContext
{
    /** @var string */
    private $backendPath;

    /** @var string */
    private $backendUsername;

    /** @var string */
    private $backendPassword;

    /** @var ShopwarePluginConnector|ShopwarePluginConnectorLt64 */
    private $connector;

    /** @var array */
    private $extensionConfig;

    /**
     * {@inheritDoc}
     */
    public function setPath($path)
    {
        $this->backendPath = $path;
    }

    /**
     * {@inheritDoc}
     */
    public function setUsername($username)
    {
        $this->backendUsername = $username;
    }

    /**
     * {@inheritDoc}
     */
    public function setPassword($password)
    {
        $this->backendPassword = $password;
    }

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
    public function visit($page): void
    {
        parent::visit($page);

        $this->closeToolbars();
    }

    /**
     * {@inheritDoc}
     */
    public function visitPath($path, $sessionName = null): void
    {
        parent::visitPath($path, $sessionName);

        $this->closeToolbars();
    }

    /**
     * {@inheritDoc}
     */
    public function checkOption($option): void
    {
        $script = <<<JS
let checkbox = document.getElementById('$option');
if (checkbox) {
    checkbox.checked = true;
}
JS;
        $this->getSession()->executeScript($script);
    }

    /**
     * @Given /^(?:|I )select payment option "([^"]+)"$/
     *
     * @param string $name
     */
    public function selectCheckoutPaymentOption(string $name): void
    {
        $script = <<<JS
(function () {
    let targetText = '$name';
    let methodNames = document.querySelectorAll('.payment-methods .payment-method .payment-method-description');
    for (let i = 0; i < methodNames.length; i++) {
        if (methodNames.hasOwnProperty(i) && methodNames[i].innerText.toLowerCase().indexOf(targetText.toLowerCase()) >= 0) {
            methodNames[i].click && methodNames[i].click();
            return;
        }
    }
    throw new Error('Payment option ' + targetText + ' not found on this page');
})()
JS;
        if ($this->connector instanceof ShopwarePluginConnectorLt64) {
            $script = <<<JS
(function () {
    let targetText = '$name';
    let methodNames = document.querySelectorAll('#confirmPaymentForm .payment-method-description strong');
    for (let i = 0; i < methodNames.length; i++) {
        if (methodNames.hasOwnProperty(i) && methodNames[i].innerText.toLowerCase().indexOf(targetText.toLowerCase()) >= 0) {
            methodNames[i].parentElement.parentElement.previousElementSibling.checked = true;
            return;
        }
    }
    throw new Error('Payment option ' + targetText + ' not found on this page');
})()
JS;
        }

        $this->getSession()->executeScript($script);
    }

    /**
     * @Given /^(?:|I )switch to currency ([A-Z]{3,3})$/
     *
     * @param string $symbol
     */
    public function switchToCurrency(string $symbol): void
    {
        $selector = "[aria-labelledby=currenciesDropdown-top-bar] [title=$symbol] input[type=radio]";
        $script = <<<JS
let radio = document.querySelector('$selector');
if (radio) {
    radio.checked = true;
} else {
    throw new Error('Currency select element not found on this page');
}
let form = document.querySelector('form.currency-form');
form.submit();
JS;
        $this->getSession()->executeScript($script);
    }

    /**
     * @Given /^(?:|I )should see payment option "([^"]+)"$/
     *
     * @param string $name
     */
    public function iShouldSeePaymentOption(string $name): void
    {
        $script = <<<JS
(function () {
    let targetText = '$name';
    let methodNames = document.querySelectorAll('#confirmPaymentForm .payment-method-description strong');
    for (let i = 0; i < methodNames.length; i++) {
        if (methodNames.hasOwnProperty(i) && methodNames[i].innerText.indexOf(targetText) >= 0) {
            return;
        }
    }
    throw new Error('Payment option ' + targetText + ' not found on this page');
})()
JS;
        $this->getSession()->executeScript($script);
    }

    /**
     * @Given /^(?:|I )log in into admin section$/
     *
     * @throws \Exception
     */
    public function loginIntoAdminSection(): void
    {
        $this->visitPath($this->backendPath);
        $driver = $this->getSession()->getDriver();
        $dashboardGreetingsVariants = [
            '.sw-dashboard-index__intro-content h1' => 'Welcome to Shopware 6',
            '.sw-dashboard-index__welcome-title' => 'Welcome to Shopware',
        ];
        foreach ($dashboardGreetingsVariants as $selector => $dashboardLoadedText) {
            $condition = "document.querySelectorAll('$selector').length > 0";
            $this->getSession()->wait(3000, $condition);
            $elementExists = $driver->evaluateScript($condition);
            if ($elementExists) {
                $elementTextContent = trim($driver->evaluateScript(
                    "document.querySelectorAll('$selector')[0].textContent"
                ));
                if (false !== stripos($elementTextContent, $dashboardLoadedText)) {
                    return;
                }
            }
        }

        $this->fillField('sw-field--username', $this->backendUsername);
        $this->fillField('sw-field--password', $this->backendPassword);

        $this->pressButton('Log in');
        $this->getSession()->wait(5000, $condition);
    }

    /**
     * @Given /^(?:|I )select product quantity (\d+)$/
     *
     * @param string $qty
     */
    public function fillInProductQuantity(string $qty): void
    {
        $script = <<<JS
let select = document.querySelector('select.product-detail-quantity-select');
if (!select) {
    throw new Error('Product qty select element not found on this page');
}
select.value = '$qty';
JS;
        $this->getSession()->executeScript($script);
    }

    /**
     * @Given /^(?:|I )should see element with the following CSS locator "([^"]+)"$/
     *
     * @param string $locator
     */
    public function assertElementExistsByCssLocator(string $locator): void
    {
        $this->waitTillElementExists($locator);
        $script = <<<JS
if (!document.querySelector('$locator')) {
    throw new Error('Element not found by $locator');
}
JS;
        $this->getSession()->executeScript($script);
    }

    /**
     * @Then /^(?:|I )expect export button is (not\s)?disabled$/
     *
     * @param bool $not
     * @throws AssertionFailedException
     */
    public function elementIsDisabledOrNot($not = false)
    {
        $buttonSelector = 'button[name="PevrPayeverIntegration.config.productsAndInventoryExport"]';
        $script = <<<JS
(function () {
    let result = false;
    let elements = document.querySelectorAll('$buttonSelector');
    if (elements.length > 0) {
        result = elements[0].className.indexOf('sw-button--disabled') > 0;
    }

    return result;
})();
JS;
        $result =  $this->getSession()->evaluateScript($script);
        Assertion::true(
            $not ? !$result : $result,
            'Export button is ' . ($result ? 'disabled' : 'enabled')
        );
    }

    /**
     * @Given /^(?:|I )fill product gross price with "(.*)"$/
     *
     * @param string|float $price
     */
    public function setProductGrossPrice($price)
    {
        $selector = 'input[name=sw-price-field-gross]';
        $script = <<<JS
(function () {
let length = document.querySelectorAll('$selector').length
let key = 0;
if (length >= 3) {
    key = 1;
}
let el = document.querySelectorAll('$selector')[key];
el.value = $price;
let elEvent = new Event('input');
el.dispatchEvent(elEvent);
})();
JS;
        $this->getSession()->executeScript($script);
    }

    /**
     * @Given /^(?:|I )fill product net price with "(.*)"$/
     *
     * @param string|float $price
     */
    public function setProductNetPrice($price)
    {
        $selector = 'input[name=sw-price-field-net]';
        $script = <<<JS
(function () {
let length = document.querySelectorAll('$selector').length
let key = 0;
if (length >= 3) {
    key = 1;
}
let el = document.querySelectorAll('$selector')[key];
el.value = $price;
let elEvent = new Event('input');
el.dispatchEvent(elEvent);
})();
JS;
        $this->getSession()->executeScript($script);
    }

    /**
     * @Given /^(?:|I )fill product sku with "(.*)"$/
     *
     * @param string $sku
     */
    public function clearSkuField($sku)
    {
        $this->waitTillElementExists('#sw-field--product-productNumber');
        $this->wait(1);
        $script = <<<JS
(function () {
    let el = document.querySelector('#sw-field--product-productNumber');
    if (el) {
        el.value = '$sku';
        let elEvent = new Event('input');
        el.dispatchEvent(elEvent);
    }
})();
JS;
        $this->getSession()->executeScript($script);
        $this->wait(1);
    }

    /**
     * Close Symfony debug toolbar and and cookie notice bar
     * so they won't prevent us from clicking elements
     */
    private function closeToolbars(): void
    {
        $script = <<<JS
let debugBtn = document.querySelector('.hide-button');
if (debugBtn && debugBtn.click) {
    debugBtn.click();
}
let cookieCloseBtn = document.querySelector('.js-cookie-permission-button button');
if (cookieCloseBtn && cookieCloseBtn.click) {
    cookieCloseBtn.click();
}
JS;
        $this->getSession()->executeScript($script);
    }
}
