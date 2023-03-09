@pluginIsEnabled @cleanProducts @thirdPartySubscribed @resetSession @products @6.1
Feature: Executing outward product actions

  Background:
    Given I set plugin config "PevrPayeverIntegration.config.productsSyncMode" value to "cron"
    And I clear cache
    Then I reset the requests storage
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
    And I clear products sync queue
    And I click on CSS locator ".sw-button-process__content"
    And I wait 5 seconds
    And the request stack should be empty
    And I wait sync queue exists and populated with size 2
    When I run products sync cronjob
    And I wait request stack exists and populated with size 3
    And the requests sequence contains:
      | path             | method | json_body                                                                                                                                                                                                                                                                                                                                                               |
      | ~/api/inventory~ | POST   |                                                                                                                                                                                                                                                                                                                                                                         |
      | ~/api/product~   | PUT    | {"externalId":"*","images":[],"imagesUrl":[],"active":true,"categories":[],"currency":"EUR","title":"Inward product title","description":"","price":9.35,"salePrice":10,"onSales":false,"sku":"OUTWRD-1","type":"physical","variants":[],"shipping":{"measure_mass":"kg","measure_size":"cm","free":false,"general":false,"weight":0,"width":0,"length":0,"height":0}}  |

  Scenario: Update product
    And I click on CSS locator ".sw-button-process__content"
    And I wait 5 seconds
    And I clear products sync queue
    Then I reset the requests storage
    And I wait till element exists "input[name="sw-field--product-name"]"
    And I wait 5 seconds
    And I fill in the following:
      | sw-field--product-name          | Inward product title modified |
      | sw-field--product-stock         | 5                             |
    And I press "Save"
    And the request stack should be empty
    And I wait sync queue exists and populated with size 2
    When I run products sync cronjob
    And I wait request stack exists and populated with size 3
    And the requests sequence contains:
      | path             | method | json_body                                                                                                                                                                                                                                                                                                                                                                        |
      | ~/api/inventory~ | POST   |                                                                                                                                                                                                                                                                                                                                                                                  |
      | ~/api/product~   | PUT    | {"externalId":"*","images":[],"imagesUrl":[],"active":true,"categories":[],"currency":"EUR","title":"Inward product title modified","description":"","price":9.35,"salePrice":10,"onSales":false,"sku":"OUTWRD-1","type":"physical","variants":[],"shipping":{"measure_mass":"kg","measure_size":"cm","free":false,"general":false,"weight":0,"width":0,"length":0,"height":0}}  |

  Scenario: Delete product
    And I clear products sync queue
    And I click on CSS locator ".sw-button-process__content"
    And I wait 5 seconds
    And I wait sync queue exists and populated with size 2
    And I clear products sync queue
    Then I reset the requests storage
    And I am on product grid page
    And I fill in the following:
      | Search products... | Inward product |
    And I wait 5 seconds
    And I click on CSS locator ".sw-data-grid__actions-menu .sw-context-button__button"
    And I wait 1 seconds
    And I click on CSS locator ".sw-context-menu-item.sw-context-menu-item--danger"
    And I wait 1 seconds
    And I click on CSS locator ".sw-button.sw-button--primary.sw-button--small"
    And I wait sync queue exists and populated with size 1
    And the request stack should be empty
    When I run products sync cronjob
    And I wait request stack exists and populated with size 1
    And the requests sequence contains:
      | path           | method | json_body          |
      | ~/api/product~ | DELETE | {"sku":"OUTWRD-1"} |
