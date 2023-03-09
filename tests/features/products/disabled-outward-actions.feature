@pluginIsEnabled @cleanProducts @thirdPartySubscribed @resetSession @products @6.4
Feature: Omit executing outward product actions when sync is disabled

  Background:
    Given I set plugin config "PevrPayeverIntegration.config.isProductsSyncEnabled" value to ""
    And I reset the requests storage
    And I log in into admin section
    And I open product form page
    And I wait till element exists "input[name="sw-field--product-name"]"
    And I wait 5 seconds
    And I fill in the following:
      | sw-field--product-name          | Inward product |
      | sw-field--product-productNumber | OUTWRD-1       |
    Then I scroll ".sw-product-detail-base__prices" into view
    And I select "Standard rate" from "sw-field--product-taxId"
    And I click on CSS locator ".sw-list-price-field.sw-list-price-field__vertical .icon--default-lock-closed"
    And I fill in the following:
      | sw-price-field-gross            | 10             |
      | sw-price-field-net              | 9.35           |
    Then I scroll ".sw-product-detail-base__deliverability" into view
    And I fill in the following:
      | sw-field--product-stock         | 3              |

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
      | sw-field--product-name          | Inward product title modified |
    Then I scroll ".sw-product-detail-base__deliverability" into view
    And I fill in the following:
      | sw-field--product-stock         | 5                             |
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
    And I click on CSS locator ".sw-button.sw-button--danger.sw-button--small"
    Then the request stack should be empty
