@pluginIsInstalled @javascript @resetSession @baseScenarios @configuration @6.1
Feature: Configuration
  In order to use plugin
  As merchant
  I need to be able to manage plugin configuration

  Scenario: Setup sandbox keys and synchronize
    Given I log in into admin section
    And I open plugin configuration page
    Then I should see the following:
      | title             |
      | Enable Sandbox    |
      | Client ID         |
      | Client Secret     |
      | Business UUID     |
      | Iframe            |
      | Checkout Language |
    And I set plugin config "PevrPayeverIntegration.config.clientId" value to ""
    And I set plugin config "PevrPayeverIntegration.config.clientSecret" value to ""
    And I set plugin config "PevrPayeverIntegration.config.businessUuid" value to ""
    And I click on CSS locator ".sw-plugin-config__set-sandbox-action .sw-button__content"
    And I wait till element exists ".sw-plugin-config__set-sandbox-action .sw-button__content"
    And I wait 10 seconds
    Then plugin config "PevrPayeverIntegration.config.clientId" value must be equal to "1454_2ax8i5chkvggc8w00g8g4sk80ckswkw0c8k8scss40o40ok4sk"
    And plugin config "PevrPayeverIntegration.config.clientSecret" value must be equal to "22uvxi05qlgk0wo8ws8s44wo8ccg48kwogoogsog4kg4s8k8k"
    And plugin config "PevrPayeverIntegration.config.businessUuid" value must be equal to "payever"
    And I click on CSS locator ".sw-button.sw-plugin-config__save-action .sw-button__content"
    And I wait payment methods count is 27
    And I connect payment methods to sales channel
    And I wait request stack exists and populated with size 1
    And I wait 15 seconds
    And the following payment methods must exist:
      | method_code              | variant_id                           | active |
      | santander_installment_dk | 0434af05-3770-4867-8b5f-7f5472b32651 | true   |
      | santander_installment_dk | 6f636e95-f47d-40c4-8548-4e395adb4341 | true   |
      | santander_installment_dk | 6f636e95-f41d-40c4-8548-4e395adb4341 | true   |
      | santander_installment_se | 0434af05-3770-4867-8b5f-7f5472b32652 | true   |
      | santander_installment_se | 6f636e95-f47d-40c4-8548-4e395adb4342 | true   |
      | santander_installment_no | 0434af05-3770-4867-8b5f-7f5472b32653 | true   |
      | santander_installment_no | 6f636e95-f47d-40c4-8548-4e395adb4343 | true   |
      | santander_installment    | 0434af05-3770-4867-8b5f-7f5472b32654 | true   |
      | santander_installment    | 6f636e95-f47d-40c4-8548-4e395adb4344 | true   |
      | santander_installment    | 6f636e95-f11d-40c4-8548-4e395adb4344 | true   |
      | santander_invoice_no     | 0434af05-3770-4867-8b5f-7f5472b32655 | true   |
      | santander_invoice_no     | 6f636e95-f47d-40c4-8548-4e395adb4345 | true   |
      | santander_invoice_de     | 0434af05-3770-4867-8b5f-7f5472b32656 | true   |
      | santander_invoice_de     | 6f636e95-f47d-40c4-8548-4e395adb4346 | true   |
      | santander_factoring_de   | 0434af05-3770-4867-8b5f-7f5472b32657 | true   |
      | stripe                   | 0434af05-3770-4867-8b5f-7f5472b32658 | true   |
      | stripe_directdebit       | 0434af05-3770-4867-8b5f-7f5472b32659 | true   |
      | paymill_creditcard       | 0434af05-3770-4867-8b5f-7f5472b32610 | true   |
      | paymill_directdebit      | 0434af05-3770-4867-8b5f-7f5472b32611 | true   |
      | sofort                   | 0434af05-3770-4867-8111-7f5472b32612 | true   |
      | payex_creditcard         | 0434af05-3770-4867-8b5f-7f5472b32612 | true   |
      | payex_faktura            | 0434af05-3770-4867-8b5f-7f5472b32613 | true   |
      | paypal                   | 0434af05-3770-4867-8b5f-7f5472b32614 | true   |
    And the requests sequence contains:
      | path                                                        | method |
      | ~/api/shop/oauth/payever/payment-options/variants/shopware~ | GET    |
