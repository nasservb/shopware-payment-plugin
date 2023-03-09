@pluginIsEnabled @cleanProducts @thirdPartySubscribed @resetSession @products
Feature: Handling inward product actions
  Background:
    Given I set plugin config "PevrPayeverIntegration.config.productsSyncMode" value to "cron"

  Scenario Outline: Manage simple product
    Given I clear cache
    And the product with SKU "PROD2" should not exist
    And I clear products sync queue
    When I execute third-party action "<initial_action>" for business "payever" with fixture "third-party/create-product"
    Then the product with SKU "PROD2" should not exist
    And I wait sync queue exists and populated with size 1
    When I run products sync cronjob
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
    And I clear products sync queue
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
    And I wait sync queue exists and populated with size 1
    Then I run products sync cronjob
    And the product with SKU "PROD2" must have the following field values:
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
      # Create product on "update-product" without prior "create-product"
      | update-product |

  Scenario: Manage inventory for simple product
    Given I clear cache
    And the product with SKU "PROD2" should not exist
    And I clear products sync queue
    Then I execute third-party action "create-product" for business "payever" with fixture "third-party/create-product"
    And the product with SKU "PROD2" should not exist
    And I wait sync queue exists and populated with size 1
    Then I run products sync cronjob
    Then the product with SKU "PROD2" should exist
    And the product with SKU "PROD2" must have inventory tracking disabled
    And I clear products sync queue
    When I execute third-party action "set-inventory" for business "payever" with body:"
      """
      {
        "sku": "PROD2",
        "stock": 11
      }
      """
    Then the product with SKU "PROD2" must have inventory tracking disabled
    And I wait sync queue exists and populated with size 1
    When I run products sync cronjob
    Then the product with SKU "PROD2" must have inventory tracking enabled
    And the product with SKU "PROD2" must have inventory quantity "11"
    And I clear products sync queue
    When I execute third-party action "set-inventory" for business "payever" with body:"
      """
      {
        "sku": "PROD2",
        "stock": 15
      }
      """
    Then the product with SKU "PROD2" must have inventory quantity "11"
    And I wait sync queue exists and populated with size 1
    Then I run products sync cronjob
    Then the product with SKU "PROD2" must have inventory quantity "15"
    And I clear products sync queue
    When I execute third-party action "add-inventory" for business "payever" with body:"
      """
      {
        "sku": "PROD2",
        "stock": 19,
        "quantity": 4
      }
      """
    Then the product with SKU "PROD2" must have inventory quantity "15"
    And I wait sync queue exists and populated with size 1
    Then I run products sync cronjob
    Then the product with SKU "PROD2" must have inventory quantity "19"
    And I clear products sync queue
    When I execute third-party action "subtract-inventory" for business "payever" with body:"
      """
      {
        "sku": "PROD2",
        "stock": 0,
        "quantity": 19
      }
      """
    Then the product with SKU "PROD2" must have inventory quantity "19"
    And I wait sync queue exists and populated with size 1
    And I run products sync cronjob
    And the product with SKU "PROD2" must have inventory tracking disabled
    And I clear products sync queue
    When I execute third-party action "subtract-inventory" for business "payever" with body:"
      """
      {
        "sku": "PROD2",
        "stock": -1,
        "quantity": 1
      }
      """
    And the product with SKU "PROD2" must have inventory tracking disabled
    And I wait sync queue exists and populated with size 1
    And I run products sync cronjob
    And the product with SKU "PROD2" must have inventory tracking disabled

  Scenario: Delete product with variants
    Given I clear cache
    And the product with SKU "PROD1" should not exist
    And I clear products sync queue
    When I execute third-party action "create-product" for business "payever" with fixture "third-party/create-product-with-variants"
    Then the product with SKU "PROD1" should not exist
    And I wait sync queue exists and populated with size 1
    And I run products sync cronjob
    Then the product with SKU "PROD1" should exist
    And the product with SKU "PROD1" must have the variant with SKU "PROD1-VAR1"
    And the product with SKU "PROD1" must have the variant with SKU "PROD1-VAR2"
    And I clear products sync queue
    When I execute third-party action "remove-product" for business "payever" with body:"
      """
      {
        "sku": "PROD1-VAR2"
      }
      """

    Then the product with SKU "PROD1" should exist
    And the product with SKU "PROD1" must have the variant with SKU "PROD1-VAR2"
    And the product with SKU "PROD1" must have the variant with SKU "PROD1-VAR1"
    And I wait sync queue exists and populated with size 1
    And I run products sync cronjob
    Then the product with SKU "PROD1" should exist
    And the product with SKU "PROD1" must not have the variant with SKU "PROD1-VAR2"
    And the product with SKU "PROD1" must have the variant with SKU "PROD1-VAR1"
    And I clear products sync queue
    When I execute third-party action "remove-product" for business "payever" with body:"
      """
      {
        "sku": "PROD1-VAR1"
      }
      """
    Then the product with SKU "PROD1" should exist
    And the product with SKU "PROD1" must not have the variant with SKU "PROD1-VAR2"
    And the product with SKU "PROD1" must have the variant with SKU "PROD1-VAR1"
    And I wait sync queue exists and populated with size 1
    And I run products sync cronjob
    Then the product with SKU "PROD1" should exist
    And the product with SKU "PROD1" must not have the variant with SKU "PROD1-VAR2"
    And the product with SKU "PROD1" must not have the variant with SKU "PROD1-VAR1"
    And I clear products sync queue
    When I execute third-party action "remove-product" for business "payever" with body:"
      """
      {
        "sku": "PROD1"
      }
      """
    Then the product with SKU "PROD1" should exist
    And the product with SKU "PROD1" must not have the variant with SKU "PROD1-VAR2"
    And the product with SKU "PROD1" must not have the variant with SKU "PROD1-VAR1"
    And I wait sync queue exists and populated with size 1
    And I run products sync cronjob
    Then the product with SKU "PROD1" should not exist
