@pluginIsEnabled @cleanProducts @thirdPartySubscribed @resetSession @products @6.1
Feature: Executing outward product actions
  Background:
    Given I set plugin config "PevrPayeverIntegration.config.productsSyncMode" value to "instant"
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

  Scenario: Create product
    And I click on CSS locator ".sw-button-process__content"
    And I wait 5 seconds
    And I wait request stack exists and populated with size 5
    And the requests sequence similar to:
      | path                            | method | json_body                                                                                                                                                                                                                                                                                                                      |
      | ~/api/inventory/externalIdHash~ | POST   |                                                                                                                                                                                                                                                                                                                                |
      | ~/api/product/externalIdHash~   | PUT    | {"externalId":"*","images":[],"imagesUrl":[],"active":true,"categories":[],"currency":"EUR","title":"Inward product","price":10,"onSales":false,"sku":"OUTWRD-1","type":"physical","variants":[],"shipping":{"measure_mass":"kg","measure_size":"mm","free":false,"general":false,"weight":0,"width":0,"length":0,"height":0}} |

 Scenario: Delete product
    And I click on CSS locator ".sw-button-process__content"
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
    And I wait request stack exists and populated with size 3
    And the requests sequence similar to:
      | path                            | method | json_body                                                                                                                                                                                                                                                                                                                      |
      | ~/api/inventory/externalIdHash~ | POST   |                                                                                                                                                                                                                                                                                                                                |
      | ~/api/product/externalIdHash~   | PUT    | {"externalId":"*","images":[],"imagesUrl":[],"active":true,"categories":[],"currency":"EUR","title":"Inward product","price":10,"onSales":false,"sku":"OUTWRD-1","type":"physical","variants":[],"shipping":{"measure_mass":"kg","measure_size":"mm","free":false,"general":false,"weight":0,"width":0,"length":0,"height":0}} |
