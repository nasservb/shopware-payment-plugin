@pluginIsEnabled @javascript @resetSession @products
Feature: Third-party subscription management

  Background:
    Given I log in into admin section
    And I open plugin configuration page
    And I reset the requests storage

  @thirdPartyUnsubscribed
  Scenario: Subscribe
    And I wait 1 seconds
    And I clear cache
    And I wait till element exists "button[name="PevrPayeverIntegration.config.productsAndInventoryToggleSubscription""
    And I wait 3 seconds
    And I click on CSS locator ".sw-products-and-inventory-toggle-subscription-button"
    And I wait request stack exists and populated with size 7
    And I wait till element exists "button[name="PevrPayeverIntegration.config.productsAndInventoryToggleSubscription""
    And I wait 1 seconds
    And I clear cache
    And I wait request stack exists and populated with size 7
    And plugin config "PevrPayeverIntegration.config.isProductsSyncEnabled" value must be equal to "true"
    And I wait request stack exists and populated with size 7
    Then the requests sequence contains:
      | path                                             | method  | json_body                                                                                                                                                                                                                                                                                                                                                                                                     |
      | */api/business/payever/connection/authorization* | GET     |                                                                                                                                                                                                                                                                                                                                                                                                               |
      | */api/business/payever/integration/shopware*     | POST    | {"businessUuid":"payever","externalId":"*","thirdPartyName":"shopware","actions":[{"name":"create-product","url":"*","method":"POST"},{"name":"update-product","url":"*","method":"POST"},{"name":"remove-product","url":"*","method":"POST"},{"name":"add-inventory","url":"*","method":"POST"},{"name":"set-inventory","url":"*","method":"POST"},{"name":"subtract-inventory","url":"*","method":"POST"}]} |
    And I expect export button is not disabled

  @thirdPartySubscribed
  Scenario: Unsubscribe
    And I wait 1 seconds
    And I clear cache
    And I wait till element exists "button[name="PevrPayeverIntegration.config.productsAndInventoryToggleSubscription""
    And I wait 3 seconds
    And I click on CSS locator ".sw-products-and-inventory-toggle-subscription-button"
    And I wait request stack exists and populated with size 5
    And I wait till element exists "button[name="PevrPayeverIntegration.config.productsAndInventoryToggleSubscription""
    And I wait request stack exists and populated with size 5
    And I clear cache
    And I wait 5 seconds
    And plugin config "PevrPayeverIntegration.config.isProductsSyncEnabled" value must be equal to ""
    Then the requests sequence contains:
      | path                                             | method |
      | */api/business/payever/connection/authorization* | DELETE |
    And I wait till element exists "button[name="PevrPayeverIntegration.config.productsAndInventoryExport""
    And I wait 1 seconds
    And I expect export button is disabled

  @thirdPartyUnsubscribed
  Scenario: Back subscribe
    And I wait 1 seconds
    And I clear cache
    And I wait till element exists "button[name="PevrPayeverIntegration.config.productsAndInventoryToggleSubscription""
    And I wait 3 seconds
    And I click on CSS locator ".sw-products-and-inventory-toggle-subscription-button"
    And I wait till element exists "button[name="PevrPayeverIntegration.config.productsAndInventoryToggleSubscription""
    And I wait request stack exists and populated with size 5
    And I wait till element exists "button[name="PevrPayeverIntegration.config.productsAndInventoryToggleSubscription""
    And I wait 1 seconds
    And I clear cache
