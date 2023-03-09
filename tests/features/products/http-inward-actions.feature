@pluginIsEnabled @cleanProducts @thirdPartySubscribed @resetSession @products
Feature: Handling inward product actions
  Background:
    Given I set plugin config "PevrPayeverIntegration.config.productsSyncMode" value to "instant"

  Scenario Outline: Manage simple product
    Given I clear cache
    And the product with SKU "PROD2" should not exist
    And I expect the next third-party action to fail with status 200
    When I execute third-party action "<initial_action>" for business "payever" with fixture "third-party/create-product"
    Then the product with SKU "PROD2" should exist
    And the product with SKU "PROD2" must have the following field values:
      | name          | Main Product 2             |
      | description   | Main Product 2 description |
      | active        | 1                          |
      | weight        | 21                         |
      | width         | 50                         |
      | length        | 50                         |
      | height        | 50                         |
      | visibility    | 30                         |
      | net           | 336.13                     |
      | gross         | 400                        |
      | linked        | true                       |
      | vatRate       | 19                         |
      | currency      | EUR                        |
    And the product with SKU "PROD2" must have inventory tracking disabled
    And the product with SKU "PROD2" must be assigned to the category "Stub goods category 1"
    When I execute third-party action "update-product" for business "payever" with fixture "third-party/create-product" and body:
      """
      {
        "active": false,
        "price": 390,
        "on_sales": true,
        "salePrice": 377,
        "title": "Main Product 2 Updated",
        "description": "Main Product 2 description updated"
      }
      """
    Then the product with SKU "PROD2" must have the following field values:
      | name          | Main Product 2 Updated             |
      | description   | Main Product 2 description updated |
      | active        | 0                                  |
      | net           | 327.73                             |
      | gross         | 377                                |
      | linked        |                                    |
    And the product with SKU "PROD2" must have inventory tracking disabled

  Examples:
    | initial_action |
    | create-product |
    | update-product |

  Scenario: Open product detail page
    Given I clear cache
    And the product with SKU "PROD2" should not exist
    When I execute third-party action "create-product" for business "payever" with fixture "third-party/create-product"
    Then the product with SKU "PROD2" should exist
    And I open the product with SKU "PROD2" detail page
    And I should see "Main Product 2"

  Scenario: Convert price to the base currency by payever rates
    Given I clear cache
    And the product with SKU "PROD2" should not exist
    When I execute third-party action "create-product" for business "payever" with fixture "third-party/create-product" and body:
      """
      {
        "currency": "NOK",
        "price": 5000,
        "on_sales": false
      }
      """
    Then the product with SKU "PROD2" should exist
    And I wait request stack exists and populated with size 1
    And the last requests sequence equals to:
      | method | path                  |
      | GET    | /api/rest/v1/currency |
    And the product with SKU "PROD2" must have the following field values:
      | net       | 442.24 |
      | gross     | 526.27 |
      | linked    | true   |
    When I execute third-party action "create-product" for business "payever" with fixture "third-party/create-product" and body:
      """
      {
        "currency": "NOK",
        "price": 5000,
        "on_sales": true,
        "salePrice": 3900
      }
      """
    Then the last requests sequence equals to:
      | method | path                  |
      | GET    | /api/rest/v1/currency |
    And the product with SKU "PROD2" must have the following field values:
      | net     | 442.24 |
      | gross   | 410.49 |
      | linked  |        |

  Scenario: Manage product with variants
    Given I clear cache
    And the product with SKU "PROD1" should not exist
    When I execute third-party action "create-product" for business "payever" with fixture "third-party/create-product-with-variants"
    And I clear cache
    And I wait 5 seconds
    Then the product with SKU "PROD1" should exist
    And the product with SKU "PROD1" must be assigned to the following categories:
      | Stub goods category 1 |
      | Stub goods category 2 |
    And the product with SKU "PROD1" must have inventory tracking disabled
    And the product with SKU "PROD1" must have the following field values:
      | name          | Main Product 1             |
      | description   | Main Product 1 description |
      | active        | 1                          |
      | weight        | 10                         |
      | width         | 80                         |
      | length        | 70                         |
      | height        | 60                         |
      | visibility    | 30                         |
      | net           | 0                          |
      | gross         | 0                          |
      | linked        | true                       |
      | vatRate       | 19                         |
      | currency      | EUR                        |
    Then the product with SKU "PROD1" must have the variant with SKU "PROD1-VAR1"
    And the product with SKU "PROD1-VAR1" must have inventory tracking disabled
    And the product with SKU "PROD1-VAR1" must have the following field values:
      | name          | Variant 1                  |
      | description   | Variant 1 description      |
      | active        | 1                          |
      | visibility    | 30                         |
      | net           | 588.24                     |
      | gross         | 600                        |
      | linked        |                            |
      | vatRate       | 19                         |
      | currency      | EUR                        |
    And the product variant with SKU "PROD1-VAR1" must have the following option values:
      | Attr 1        | Attr 1 value 1             |
      | Attr 2        | Attr 2 value 3             |
      | Attr 3        | Attr 3 value 5             |
    And I open the product with SKU "PROD1-VAR1" detail page
    And I should see the following:
      | title              |
      | Variant 1          |
      | Attr 1             |
      | Attr 2             |
      | Attr 3             |
      | Attr 1 value 1     |
      | Attr 2 value 3     |
      | Attr 3 value 5     |

  Scenario: Manage inventory for simple product
    Given I clear cache
    And the product with SKU "PROD2" should not exist
    When I execute third-party action "create-product" for business "payever" with fixture "third-party/create-product"
    Then the product with SKU "PROD2" should exist
    And the product with SKU "PROD2" must have inventory tracking disabled
    When I execute third-party action "set-inventory" for business "payever" with body:"
      """
      {
        "sku": "PROD2",
        "stock": 11
      }
      """
    Then the product with SKU "PROD2" must have inventory tracking enabled
    And the product with SKU "PROD2" must have inventory quantity "11"
    When I execute third-party action "set-inventory" for business "payever" with body:"
      """
      {
        "sku": "PROD2",
        "stock": 15
      }
      """
    Then the product with SKU "PROD2" must have inventory quantity "15"
    When I execute third-party action "add-inventory" for business "payever" with body:"
      """
      {
        "sku": "PROD2",
        "stock": 19,
        "quantity": 4
      }
      """
    Then the product with SKU "PROD2" must have inventory quantity "19"
    When I execute third-party action "subtract-inventory" for business "payever" with body:"
      """
      {
        "sku": "PROD2",
        "stock": 0,
        "quantity": 19
      }
      """
    And the product with SKU "PROD2" must have inventory tracking disabled
    When I execute third-party action "subtract-inventory" for business "payever" with body:"
      """
      {
        "sku": "PROD2",
        "stock": -1,
        "quantity": 1
      }
      """
    And the product with SKU "PROD2" must have inventory tracking disabled

  Scenario: Create inventory on "add-inventory" without prior "set-inventory"
    Given I clear cache
    And the product with SKU "PROD2" should not exist
    When I execute third-party action "create-product" for business "payever" with fixture "third-party/create-product"
    Then the product with SKU "PROD2" should exist
    And the product with SKU "PROD2" must have inventory tracking disabled
    When I execute third-party action "add-inventory" for business "payever" with body:"
      """
      {
        "sku": "PROD2",
        "stock": 19,
        "quantity": 4
      }
      """
    Then the product with SKU "PROD2" must have inventory tracking enabled
    And the product with SKU "PROD2" must have inventory quantity "19"

  Scenario: Create inventory on "subtract-inventory" without prior "set-inventory"
    Given I clear cache
    And the product with SKU "PROD2" should not exist
    When I execute third-party action "create-product" for business "payever" with fixture "third-party/create-product"
    Then the product with SKU "PROD2" should exist
    And the product with SKU "PROD2" must have inventory tracking disabled
    When I execute third-party action "subtract-inventory" for business "payever" with body:"
      """
      {
        "sku": "PROD2",
        "stock": 18,
        "quantity": 5
      }
      """
    Then the product with SKU "PROD2" must have inventory tracking enabled
    And the product with SKU "PROD2" must have inventory quantity "18"

  Scenario: Manage inventory for a product with variants
    Given I clear cache
    And the product with SKU "PROD1" should not exist
    When I execute third-party action "create-product" for business "payever" with fixture "third-party/create-product-with-variants"
    Then the product with SKU "PROD1" should exist
    And the product with SKU "PROD1" must have inventory tracking disabled
    And the product with SKU "PROD1" must have the variant with SKU "PROD1-VAR1"
    And the product with SKU "PROD1-VAR1" must have inventory tracking disabled
    And the product with SKU "PROD1" must have the variant with SKU "PROD1-VAR2"
    And the product with SKU "PROD1-VAR2" must have inventory tracking disabled
    When I execute third-party action "set-inventory" for business "payever" with body:"
      """
      {
        "sku": "PROD1-VAR1",
        "stock": 20
      }
      """
    Then the product with SKU "PROD1-VAR1" must have inventory tracking enabled
    And the product with SKU "PROD1-VAR2" must have inventory tracking disabled
    When I execute third-party action "set-inventory" for business "payever" with body:"
      """
      {
        "sku": "PROD1-VAR2",
        "stock": 25
      }
      """
    Then the product with SKU "PROD1-VAR1" must have inventory tracking enabled
    And the product with SKU "PROD1-VAR1" must have inventory quantity "20"
    And the product with SKU "PROD1-VAR2" must have inventory tracking enabled
    And the product with SKU "PROD1-VAR2" must have inventory quantity "25"
    When I execute third-party action "subtract-inventory" for business "payever" with body:"
      """
      {
        "sku": "PROD1-VAR1",
        "stock": 5,
        "quantity": 15
      }
      """
    Then the product with SKU "PROD1-VAR1" must have inventory quantity "5"
    When I execute third-party action "subtract-inventory" for business "payever" with body:"
      """
      {
        "sku": "PROD1-VAR2",
        "stock": 10,
        "quantity": 15
      }
      """
    Then the product with SKU "PROD1-VAR2" must have inventory quantity "10"
    When I execute third-party action "add-inventory" for business "payever" with body:"
      """
      {
        "sku": "PROD1-VAR1",
        "stock": 25,
        "quantity": 20
      }
      """
    Then the product with SKU "PROD1-VAR1" must have inventory quantity "25"
    When I execute third-party action "add-inventory" for business "payever" with body:"
      """
      {
        "sku": "PROD1-VAR2",
        "stock": 40,
        "quantity": 30
      }
      """
    Then the product with SKU "PROD1-VAR2" must have inventory quantity "40"

  Scenario: Delete simple product
    Given I clear cache
    And the product with SKU "PROD2" should not exist
    When I execute third-party action "create-product" for business "payever" with fixture "third-party/create-product"
    Then the product with SKU "PROD2" should exist
    When I execute third-party action "remove-product" for business "payever" with body:"
      """
      {
        "sku": "PROD2"
      }
      """
    Then the product with SKU "PROD2" should not exist

  Scenario: Delete product with variants
    Given I clear cache
    And the product with SKU "PROD1" should not exist
    When I execute third-party action "create-product" for business "payever" with fixture "third-party/create-product-with-variants"
    Then the product with SKU "PROD1" should exist
    And the product with SKU "PROD1" must have the variant with SKU "PROD1-VAR1"
    And the product with SKU "PROD1" must have the variant with SKU "PROD1-VAR2"
    When I execute third-party action "remove-product" for business "payever" with body:"
      """
      {
        "sku": "PROD1-VAR2"
      }
      """
    Then the product with SKU "PROD1" should exist
    And the product with SKU "PROD1" must not have the variant with SKU "PROD1-VAR2"
    And the product with SKU "PROD1" must have the variant with SKU "PROD1-VAR1"
    When I execute third-party action "remove-product" for business "payever" with body:"
      """
      {
        "sku": "PROD1-VAR1"
      }
      """
    Then the product with SKU "PROD1" should exist
    And the product with SKU "PROD1" must not have the variant with SKU "PROD1-VAR2"
    And the product with SKU "PROD1" must not have the variant with SKU "PROD1-VAR1"
    When I execute third-party action "remove-product" for business "payever" with body:"
      """
      {
        "sku": "PROD1"
      }
      """
    Then the product with SKU "PROD1" should not exist
