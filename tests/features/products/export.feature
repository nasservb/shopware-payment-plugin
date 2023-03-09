@pluginIsEnabled @javascript @resetSession @products
Feature: Product and inventory manual export

  @thirdPartyUnsubscribed
  Scenario: Do not allow products export while sync is not enabled
    Given I log in into admin section
    And I clear cache
    And I open plugin configuration page
    And plugin config "PevrPayeverIntegration.config.isProductsSyncEnabled" value must be equal to ""
    And I reset the requests storage
    And I wait till element exists "button[name="PevrPayeverIntegration.config.productsAndInventoryExport"]"
    And I scroll 'button[name="PevrPayeverIntegration.config.productsAndInventoryExport"]' into view
    And I wait 3 seconds
    Then I expect export button is disabled

  @thirdPartyUnsubscribed
  Scenario: Disable sync on products export error
    Given I log in into admin section
    And I clear cache
    And I open plugin configuration page
    And I wait till element exists "button[name="PevrPayeverIntegration.config.productsAndInventoryToggleSubscription"]"
    And I wait 3 seconds
    And I scroll ".sw-products-and-inventory-toggle-subscription-button" into view
    And I click on CSS locator ".sw-products-and-inventory-toggle-subscription-button"
    And I wait request stack exists and populated with size 7
    And I wait till element exists "button[name="PevrPayeverIntegration.config.productsAndInventoryToggleSubscription"]"
    And I wait 1 seconds
    And I clear cache
    And I wait request stack exists and populated with size 9
    And I wait till element exists "button[name="PevrPayeverIntegration.config.productsAndInventoryExport"]"
    And I scroll 'button[name="PevrPayeverIntegration.config.productsAndInventoryExport"]' into view
    And I wait 1 seconds
    Then I expect export button is not disabled
    And I expect third-party product actions to be forbidden
    And I press "Export Shopware products"
    And I run message consumer
    Then plugin config "PevrPayeverIntegration.config.isProductsSyncEnabled" value must be equal to ""
    Given I expect third-party product actions to be allowed
    And clear products sync queue

  @thirdPartyUnsubscribed
  Scenario: Export products
    Given I log in into admin section
    And I clear cache
    And I open plugin configuration page
    And I wait till element exists "button[name="PevrPayeverIntegration.config.productsAndInventoryToggleSubscription"]"
    And I wait 3 seconds
    And I scroll ".sw-products-and-inventory-toggle-subscription-button" into view
    And I click on CSS locator ".sw-products-and-inventory-toggle-subscription-button"
    And I wait request stack exists and populated with size 7
    And I wait till element exists "button[name="PevrPayeverIntegration.config.productsAndInventoryToggleSubscription"]"
    And I wait 1 seconds
    And I clear cache
    And I wait request stack exists and populated with size 7
    And I wait till element exists "button[name="PevrPayeverIntegration.config.productsAndInventoryExport"]"
    And I scroll 'button[name="PevrPayeverIntegration.config.productsAndInventoryExport"]' into view
    And I wait 1 seconds
    Then I expect export button is not disabled
    And I press "Export Shopware products"
    And I run message consumer
    Then the requests sequence contains:
      | path             | method |
      | ~/api/inventory~ | POST   |
    # Open homepage so session reset happens on shop domain
    Given I am on the homepage
