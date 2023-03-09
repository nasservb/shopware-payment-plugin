@pluginIsInstalled @javascript @restartSession @payments @6.4
Feature: Payment actions

  Background:
    Given I expect payment action "shipping_goods" to be "allowed"
    And I expect payment action "cancel" to be "allowed"
    And I expect payment action "refund" to be "allowed"
    And I am on stub product page
    And I switch to currency EUR
    And I select product quantity 1
    And I press "Add to shopping cart"
    And I wait till element exists ".modal-backdrop.modal-backdrop-open"
    And I am on "/checkout/register/"
    And I click on CSS locator ".checkout-main [for=personalGuest]"
    And I select "Mr." from "personalSalutation"
    And I select "Germany" from "billingAddressAddressCountry"
    And I fill in the following:
        | personalFirstName            | Stub                        |
        | personalLastName             | Lastname                    |
        | personalMail                 | plugin-autotest@example.com |
        | billingAddressAddressStreet  | some street                 |
        | billingAddressAddressZipcode | 10111                       |
        | billingAddressAddressCity    | Berlin                      |
    And I press "Continue"
    Then I click on CSS locator ".confirm-checkout-collapse-trigger-label"
    # Modal animation
    And I wait 5 seconds
    And I select payment option "Santander DE Invoice"
    And I expect payment redirect url to be "success_url"
    And I expect payment status to be "STATUS_ACCEPTED"
    And I click on CSS locator "#tos"
    When I click on CSS locator "#confirmFormSubmit"
    Then I should see "Thank you for your order"
    And new order should be created
    Given I log in into admin section
    And I open order grid page

  Scenario: Place order and press shipping goods
    Given I expect payment action "shipping_goods" to be "allowed"
    Then I set order totals "captured_total" to "0"
    Then I set order totals "cancelled_total" to "0"
    Then I set order totals "refunded_total" to "0"
    And I click on CSS locator ".sw-data-grid__cell--orderNumber a"
    And I wait 1 seconds
    And I wait till element exists ".sw-order-payever-card"
    And I wait till element exists ".sw-order-payever-card #pe_ship"
    Then I scroll ".sw-order-payever-card .sw-container" into view
    When I click on CSS locator ".sw-order-payever-card #pe_ship"
    #And I wait request stack exists and populated with size 21
    And I wait 10 seconds
    Then the requests sequence contains:
        | path                           | method |
        | */api/payment/shipping-goods/* | POST   |

  Scenario: Place order and press shipping goods case disallowed
    Given I expect payment action "shipping_goods" to be "disallowed"
    And I click on CSS locator ".sw-data-grid__cell--orderNumber a"
    And I wait till element exists ".sw-order-payever-card"
    And I should not see a "#pe_ship" element

  Scenario: Place order and press cancel
    Given I expect payment action "cancel" to be "allowed"
    And I click on CSS locator ".sw-data-grid__cell--orderNumber a"
    And I wait 1 seconds
    And I wait till element exists ".sw-order-payever-card"
    And I wait till element exists ".sw-order-payever-card #pe_cancel"
    Then I scroll ".sw-order-payever-card .sw-container" into view
    When I click on CSS locator ".sw-order-payever-card #pe_cancel"
    #And I wait request stack exists and populated with size 21
    And I wait 10 seconds
    Then the requests sequence contains:
        | path                   | method |
        | */api/payment/cancel/* | POST   |

  Scenario: Place order and press cancel case disallowed
    Given I expect payment action "cancel" to be "disallowed"
    And I click on CSS locator ".sw-data-grid__cell--orderNumber a"
    And I wait till element exists ".sw-order-payever-card"
    And I should not see a "#pe_cancel" element

  Scenario: Place order, ship and press refund
    Given I expect payment action "refund" to be "allowed"
    Given I expect payment action "shipping_goods" to be "allowed"
    Then I set order totals "captured_total" to "100"
    Then I set order totals "cancelled_total" to "0"
    Then I set order totals "refunded_total" to "0"
    And I click on CSS locator ".sw-data-grid__cell--orderNumber a"
    And I wait 1 seconds
    And I wait till element exists ".sw-order-payever-card"
    And I wait till element exists ".sw-order-payever-card #pe_ship"
    Then I scroll ".sw-order-payever-card .sw-container" into view
    When I click on CSS locator ".sw-order-payever-card #pe_ship"
    And I wait 30 seconds
    And I wait till element exists ".sw-order-payever-card"
    And I wait till element exists ".sw-order-payever-card #pe_refund"
    Then I scroll ".sw-order-payever-card .sw-container" into view
    When I click on CSS locator ".sw-order-payever-card #pe_refund"
    And I wait request stack exists and populated with size 21
    And I wait 10 seconds
    Then the requests sequence contains:
        | path                   | method |
        | */api/payment/refund/* | POST   |

  Scenario: Place order and press refund case disallowed
    Given I expect payment action "refund" to be "disallowed"
    And I click on CSS locator ".sw-data-grid__cell--orderNumber a"
    And I wait till element exists ".sw-order-payever-card"
    And I should not see a "#pe_refund" element

  Scenario: Place order and press refund case legacy return
    Given I expect payment action "return" to be "allowed"
    Given I expect payment action "shipping_goods" to be "allowed"
    Then I set order totals "captured_total" to "100"
    Then I set order totals "cancelled_total" to "0"
    Then I set order totals "refunded_total" to "0"
    And I click on CSS locator ".sw-data-grid__cell--orderNumber a"
    And I wait 1 seconds
    And I wait till element exists ".sw-order-payever-card"
    And I wait till element exists ".sw-order-payever-card #pe_ship"
    Then I scroll ".sw-order-payever-card .sw-container" into view
    When I click on CSS locator ".sw-order-payever-card #pe_ship"
    And I wait 30 seconds
    And I wait till element exists ".sw-order-payever-card"
    And I wait till element exists ".sw-order-payever-card #pe_refund"
    Then I scroll ".sw-order-payever-card .sw-container" into view
    When I click on CSS locator ".sw-order-payever-card #pe_refund"
    And I wait request stack exists and populated with size 21
    And I wait 10 seconds
    Then the requests sequence contains:
        | path                   | method |
        | */api/payment/refund/* | POST   |

  Scenario: Place order and press refund case disallowed case legacy return
    Given I expect payment action "return" to be "disallowed"
    And I click on CSS locator ".sw-data-grid__cell--orderNumber a"
    And I wait till element exists ".sw-order-payever-card"
    And I should not see a "#pe_refund" element

  Scenario: Place order and cancel order
    Given I expect payment action "cancel" to be "allowed"
    Then I set order totals "captured_total" to "0"
    Then I set order totals "cancelled_total" to "0"
    Then I set order totals "refunded_total" to "0"
    Then I click on CSS locator ".sw-data-grid__cell--orderNumber a"
    And I wait 1 seconds
    And I wait till element exists ".sw-order-payever-card"
    And I wait till element exists ".sw-order-payever-card #pe_cancel"
    Then I select order status "cancel"
    And I wait till element exists ".sw-modal__header"
    And I scroll ".sw-order-state-change-modal-attach-documents__button" into view
    When I click on CSS locator ".sw-order-state-change-modal-attach-documents__button"
    And I wait request stack exists and populated with size 19
    And I wait 10 seconds
    Then the requests sequence contains:
        | path                   | method |
        | */api/payment/cancel/* | POST   |

  Scenario: Place order and cancel order case disallowed
    Given I expect payment action "cancel" to be "disallowed"
    Then I click on CSS locator ".sw-data-grid__cell--orderNumber a"
    And I wait 1 seconds
    And I wait till element exists ".sw-order-payever-card"
    And I should not see a "#pe_cancel" element
    Then I select order status "cancel"
    And I wait till element exists ".sw-modal__header"
    And I scroll ".sw-order-state-change-modal-attach-documents__button" into view
    When I click on CSS locator ".sw-order-state-change-modal-attach-documents__button"
    And I wait request stack exists and populated with size 17
    And I wait 10 seconds
    Then the requests sequence contains:
        | path                        | method |
        | ~/api/rest/v1/transactions~ | GET    |

  Scenario: Place order and complete order
    Given I expect payment action "shipping_goods" to be "allowed"
    Then I set order totals "captured_total" to "0"
    Then I set order totals "cancelled_total" to "0"
    Then I set order totals "refunded_total" to "0"
    Then I click on CSS locator ".sw-data-grid__cell--orderNumber a"
    And I wait 1 seconds
    And I wait till element exists ".sw-order-payever-card"
    And I wait till element exists ".sw-order-payever-card #pe_ship"
    Then I select delivery status "ship"
    And I wait till element exists ".sw-modal__header"
    And I scroll ".sw-order-state-change-modal-attach-documents__button" into view
    And I click on CSS locator ".sw-order-state-change-modal-attach-documents__button"
    And I wait 5 seconds
    And I select order status "process"
    And I wait till element exists ".sw-modal__header"
    And I scroll ".sw-order-state-change-modal-attach-documents__button" into view
    And I click on CSS locator ".sw-order-state-change-modal-attach-documents__button"
    And I wait 5 seconds
    And I select order status "complete"
    And I wait till element exists ".sw-modal__header"
    And I scroll ".sw-order-state-change-modal-attach-documents__button" into view
    When I click on CSS locator ".sw-order-state-change-modal-attach-documents__button"
    And I wait request stack exists and populated with size 19
    And I wait 10 seconds
    Then the requests sequence contains:
        | path                           | method |
        | */api/payment/shipping-goods/* | POST   |

  Scenario: Place order and complete order case disallowed
    Given I expect payment action "shipping_goods" to be "disallowed"
    Then I click on CSS locator ".sw-data-grid__cell--orderNumber a"
    And I wait till element exists ".sw-order-payever-card"
    And I should not see a "#pe_ship" element
    Then I select delivery status "ship"
    And I wait till element exists ".sw-modal__header"
    And I scroll ".sw-order-state-change-modal-attach-documents__button" into view
    And I click on CSS locator ".sw-order-state-change-modal-attach-documents__button"
    And I wait 5 seconds
    And I select order status "process"
    And I wait till element exists ".sw-modal__header"
    And I scroll ".sw-order-state-change-modal-attach-documents__button" into view
    And I click on CSS locator ".sw-order-state-change-modal-attach-documents__button"
    And I wait 10 seconds
    And I select order status "complete"
    And I wait till element exists ".sw-modal__header"
    And I scroll ".sw-order-state-change-modal-attach-documents__button" into view
    Given I expect payment action "shipping_goods" to be "disallowed"
    When I click on CSS locator ".sw-order-state-change-modal-attach-documents__button"
    And I wait request stack exists and populated with size 19
    And I wait 1 seconds
    Then the requests sequence contains:
        | path                        | method |
        | ~/api/rest/v1/transactions~ | GET    |

  Scenario: Do not update order status when order is partially refunded
    Given I expect payment action "refund" to be "allowed"
    Then I expect payment action "refund_partial" to be "allowed"
    Given I expect payment action "shipping_goods" to be "allowed"
    Then I set order totals "captured_total" to "100"
    Then I set order totals "cancelled_total" to "0"
    Then I set order totals "refunded_total" to "0"
    Then I click on CSS locator ".sw-data-grid__cell--orderNumber a"
    And I wait till element exists ".sw-order-payever-card"
    And I wait till element exists ".sw-order-payever-card #pe_ship"
    Then I scroll ".sw-order-payever-card .sw-container" into view
    When I click on CSS locator ".sw-order-payever-card #pe_ship"
    And I wait 30 seconds
    And I wait till element exists ".sw-order-payever-card"
    And I wait till element exists ".sw-order-payever-card #pe_refund"
    Then I scroll ".sw-order-payever-card .sw-container" into view
    And I wait 5 seconds
    And I fill refund amount
    When I click on CSS locator ".sw-order-payever-card #pe_refund"
    And I wait request stack exists and populated with size 21
    And I wait 10 seconds
    # Order status is "in_process" when shipped
    Then new order state must be "in_progress"
    And new transaction state must be "refunded_partially"

  Scenario: Place order and press shipping goods using payment items
    Given I expect payment action "shipping_goods" to be "allowed"
    Given I expect payment action "shipping_goods_partial" to be "allowed"
    Then I set order totals "captured_total" to "0"
    Then I set order totals "cancelled_total" to "0"
    Then I set order totals "refunded_total" to "0"
    And I click on CSS locator ".sw-data-grid__cell--orderNumber a"
    And I wait 1 seconds
    And I wait till element exists ".sw-order-payever-card"
    And I wait till element exists "#pe_ship_item"
    Then I should see a "#pe_ship_item" element
    Then I scroll ".sw-order-payever-card .sw-container" into view
    When I click on CSS locator "#pe_ship_item"
    #And I wait request stack exists and populated with size 21
    And I wait 10 seconds
    Then the requests sequence contains:
      | path                           | method |
      | */api/payment/shipping-goods/* | POST   |

  Scenario: Place order and cancel order using payment items
    Given I expect payment action "cancel" to be "allowed"
    Given I expect payment action "cancel_partial" to be "allowed"
    Then I set order totals "captured_total" to "0"
    Then I set order totals "cancelled_total" to "0"
    Then I set order totals "refunded_total" to "0"
    Then I click on CSS locator ".sw-data-grid__cell--orderNumber a"
    And I wait 1 seconds
    And I wait till element exists ".sw-order-payever-card"
    And I wait till element exists "#pe_cancel_item"
    Then I should see a "#pe_cancel_item" element
    Then I scroll ".sw-order-payever-card .sw-container" into view
    When I click on CSS locator "#pe_cancel_item"
    #And I wait request stack exists and populated with size 21
    And I wait 10 seconds
    Then the requests sequence contains:
      | path                   | method |
      | */api/payment/cancel/* | POST   |

  Scenario: Place order, ship and press refund using payment items
    Given I expect payment action "refund" to be "allowed"
    Given I expect payment action "refund_partial" to be "allowed"
    Given I expect payment action "shipping_goods" to be "allowed"
    Given I expect payment action "shipping_goods_partial" to be "allowed"
    Then I set order totals "captured_total" to "0"
    Then I set order totals "cancelled_total" to "0"
    Then I set order totals "refunded_total" to "0"
    And I click on CSS locator ".sw-data-grid__cell--orderNumber a"
    And I wait 1 seconds
    And I wait till element exists ".sw-order-payever-card"
    And I wait till element exists "#pe_ship_item"
    Then I should see a "#pe_ship_item" element
    Then I scroll ".sw-order-payever-card .sw-container" into view
    When I click on CSS locator "#pe_ship_item"
    And I wait 30 seconds
    And I wait till element exists ".sw-order-payever-card"
    And I wait till element exists "#pe_refund_item"
    Then I should see a "#pe_refund_item" element
    Then I scroll ".sw-order-payever-card .sw-container" into view
    When I click on CSS locator "#pe_refund_item"
    #And I wait request stack exists and populated with size 21
    And I wait 10 seconds
    Then the requests sequence contains:
      | path                   | method |
      | */api/payment/refund/* | POST   |