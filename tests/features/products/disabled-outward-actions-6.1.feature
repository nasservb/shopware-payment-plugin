@pluginIsEnabled @cleanProducts @thirdPartySubscribed @resetSession @products @6.1
Feature: Omit executing outward product actions when sync is disabled

  Background:
    Given I set plugin config "PevrPayeverIntegration.config.isProductsSyncEnabled" value to "0"
    And I reset the requests storage
    And I log in into admin section
    And I open product form page
    And I wait till element exists "input[name="sw-field--product-name"]"
    And I wait 5 seconds
    And I fill in the following:
      | sw-field--product-name          | Inward product |
      | sw-field--product-productNumber | OUTWRD-1       |
      | sw-field--product-stock         | 3              |
    And I fill product gross price with "10"
    And I click on CSS locator ".sw-list-price-field.sw-list-price-field__vertical .icon--default-lock-closed"
    And I fill product net price with "9.35"

  Scenario: Create product
    Given I clear cache
    When I press "Save"
    Then the request stack should be empty

  Scenario: Update product
    Given I clear cache
    When I press "Save"
    And I wait 5 seconds
    And I wait till element exists "input[name="sw-field--product-name"]"
    And I wait 5 seconds
    And I fill in the following:
      | sw-field--product-name          | Inward product modified |
      | sw-field--product-stock         | 5                       |
    And I press "Save"
    Then the request stack should be empty

  Scenario: Delete product
    Given I clear cache
    When I press "Save"
    And I wait 5 seconds
    And I am on product grid page
    And I fill in the following:
      | Search products... | Inward product |
    And I wait 5 seconds
    And I click on CSS locator ".sw-data-grid__actions-menu .sw-context-button__button"
    And I wait 1 seconds
    And I click on CSS locator ".sw-context-menu-item.sw-context-menu-item--danger"
    And I wait 1 seconds
    And I click on CSS locator ".sw-button.sw-button--primary.sw-button--small"
    Then the request stack should be empty
