@pluginIsEnabled @setupPaymentMethods @javascript @resetSession @payments @6.4
Feature: Payment

  Background:
    Given I am on stub product page
    And I select product quantity 4
    And I press "Add to shopping cart"
    And I wait till I see "Shopping cart"
    #Then I follow "Proceed to checkout"
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
    # TODO Find a better way to handle AJAX stuff
    And I wait 1 seconds
    Then I click on CSS locator ".confirm-checkout-collapse-trigger-label"
    # Modal animation
    And I wait 1 seconds

  Scenario: Payment methods visible
    And I should see the following:
      | title                                      |
      | Direct Debit                               |
      | SOFORT Banking                             |
      | Credit Card (0.25€ + 2.9%)                 |
      | Santander Installments - Germany Variant 2 |
      | Santander Installments - Germany Variant 3 |
      | PayPal (0.35€ + 1.9%)                      |
    But I should not see the following:
      | title                     |
      | Santander Factoring DE    |
      | Santander Invoice DE      |
      | Santander Installments NO |
      | Santander Invoice NO      |
      | Santander Installments SE |
      | Santander Installments DK |

  Scenario: Successful payment
    And I select payment option "Santander Installments - Germany Variant 3"
    And I expect payment redirect url to be "success_url"
    And I expect payment status to be "STATUS_ACCEPTED"
    And I click on CSS locator "#tos"
    When I click on CSS locator "#confirmFormSubmit"
    Then I should see "Thank you for your order"
    And new order should be created
    And new order state must be "open"
    And new transaction state must be "paid"
    And I should see "Payment method: Santander Installments"
    And I wait request stack exists and populated with size 5
    And the requests sequence contains:
      | path              | method | json_body                                                                                                         |
      | */api/payment*    | get    |                                                                                                                   |
      | */api/v2/payment* | post   | {"payment_method": "santander_installment", "variant_id": "6f636e95-f11d-40c4-8548-4e395adb4344", "amount": 1200} |

  Scenario: Successful payment case expired session
    And I select payment option "Santander Installments - Germany Variant 3"
    And I expect payment redirect url to be "none"
    And I expect payment status to be "STATUS_ACCEPTED"
    And I click on CSS locator "#tos"
    When I click on CSS locator "#confirmFormSubmit"
    And I remember redirect url "success_url"
    # the step imitates session expiration
    And I restart session
    And I visit redirect url "success_url"
    And new order should be created
    And new order state must be "open"
    And new transaction state must be "paid"

  Scenario: Cancel payment
    And I select payment option "Santander Installments - Germany Variant 3"
    And I expect payment redirect url to be "cancel_url"
    And I click on CSS locator "#tos"
    When I click on CSS locator "#confirmFormSubmit"
    Then I should see "Payment has been canceled"
    And new order should be created
    And new order state must be "open"
    And new transaction state must be "cancelled"
    And I wait request stack exists and populated with size 5
    And the requests sequence similar to:
      | path              | method | json_body                                                                                                         |
      | ~/pay/~           | get    |                                                                                                                   |
      | ~/api/v2/payment~ | post   | {"payment_method": "santander_installment", "variant_id": "6f636e95-f11d-40c4-8548-4e395adb4344", "amount": 1200} |

  Scenario: Cancel payment case expired session
    And I select payment option "Santander Installments - Germany Variant 3"
    And I expect payment redirect url to be "none"
    And I click on CSS locator "#tos"
    When I click on CSS locator "#confirmFormSubmit"
    And I remember redirect url "cancel_url"
    # the step imitates session expiration
    And I restart session
    And I visit redirect url "cancel_url"
    Then I should see "Payment has been canceled"
    And new order should be created
    And new order state must be "open"
    And new transaction state must be "cancelled"

  Scenario: Failed payment
    And I select payment option "Santander Installments - Germany Variant 3"
    And I expect payment redirect url to be "failure_url"
    And I expect payment status to be "STATUS_FAILED"
    And I click on CSS locator "#tos"
    When I click on CSS locator "#confirmFormSubmit"
    And I wait till element exists ".finish-header"
    Then I should see "please change the payment method or try again"
    And new order should be created
    And new order state must be "open"
    And new transaction state must be "failed"
    And I wait request stack exists and populated with size 5
    And the requests sequence contains:
        | path               | method | json_body                                                                                                         |
        | ~/api/payment~     | get    |                                                                                                                   |
        | ~/api/v2/payment~  | post   | {"payment_method": "santander_installment", "variant_id": "6f636e95-f11d-40c4-8548-4e395adb4344", "amount": 1200} |

  Scenario: Failed payment case expired session
    And I select payment option "Santander Installments - Germany Variant 3"
    And I expect payment redirect url to be "none"
    And I expect payment status to be "STATUS_FAILED"
    And I click on CSS locator "#tos"
    When I click on CSS locator "#confirmFormSubmit"
    And I remember redirect url "failure_url"
    # the step imitates session expiration
    And I restart session
    And I visit redirect url "failure_url"
    And new order should be created
    And new order state must be "open"
    And new transaction state must be "failed"

  Scenario: Create payment on notification
    And I select payment option "Santander Installments - Germany Variant 3"
    And I expect payment redirect url to be "none"
    And I expect payment status to be "STATUS_IN_PROCESS"
    And I click on CSS locator "#tos"
    When I click on CSS locator "#confirmFormSubmit"
    And I wait 5 seconds
    And I send payment notification
    Then new order should be created
    And new order state must be "open"
    And new transaction state must be "in_progress"
    And I expect payment status to be "STATUS_ACCEPTED"
    And I send payment notification
    And new order state must be "open"
    And new transaction state must be "paid"
    # Open homepage so session reset happens on shop domain
    And I am on the homepage

  Scenario: Create payment on notification case submit payment
    And I select payment option "PayPal"
    And I configure payment gateway
    And I expect payment gateway redirect url to be "none"
    And I expect payment status to be "STATUS_IN_PROCESS"
    And I click on CSS locator "#tos"
    When I click on CSS locator "#confirmFormSubmit"
    And I wait 5 seconds
    And I send payment notification
    Then new order should be created
    And new order state must be "open"
    And new transaction state must be "in_progress"
    And I expect payment status to be "STATUS_ACCEPTED"
    And I send payment notification
    And new order state must be "open"
    And new transaction state must be "paid"
    # Open homepage so session reset happens on shop domain
    And I am on the homepage

  Scenario: Should reject stalled notification
    And I select payment option "Santander Installments - Germany Variant 3"
    And I expect payment redirect url to be "none"
    And I expect payment status to be "STATUS_ACCEPTED"
    And I click on CSS locator "#tos"
    When I click on CSS locator "#confirmFormSubmit"
    And I wait till element exists ".finish-header"
    And I send payment notification
    Then new order should be created
    And new order state must be "open"
    And new transaction state must be "paid"
    And I expect payment status to be "STATUS_FAILED"
    When I send stalled payment notification and expect it to fail
    And new transaction state must be "paid"
    # Open homepage so session reset happens on shop domain
    And I am on the homepage

  Scenario: Create payment on notification with signature
    And I select payment option "Santander Installments - Germany Variant 3"
    And I expect payment redirect url to be "none"
    And I expect payment status to be "STATUS_IN_PROCESS"
    And I click on CSS locator "#tos"
    When I click on CSS locator "#confirmFormSubmit"
    # wait while payment page/iframe is loaded
    And I wait 10 seconds
    And I send payment notification with signature for "PevrPayeverIntegration.config.clientId" and "PevrPayeverIntegration.config.clientSecret"
    Then new order should be created
    And new order state must be "open"
    And new transaction state must be "in_progress"
    And I expect payment status to be "STATUS_ACCEPTED"
    And I send payment notification with signature for "PevrPayeverIntegration.config.clientId" and "PevrPayeverIntegration.config.clientSecret"
    And new order state must be "open"
    And new transaction state must be "paid"
    # Open homepage so session reset happens on shop domain
    And I am on the homepage

  Scenario: Create payment on notification with invalid signature
    And I select payment option "Santander Installments - Germany Variant 3"
    And I expect payment redirect url to be "none"
    And I expect payment status to be "STATUS_ACCEPTED"
    And I click on CSS locator "#tos"
    When I click on CSS locator "#confirmFormSubmit"
    # wait while payment page/iframe is loaded
    And I wait 10 seconds
    And I send payment notification with signature for "PevrPayeverIntegration.config.clientId" and "PevrPayeverIntegration.config.clientSecret"
    Then new order should be created
    And new order state must be "open"
    And new transaction state must be "paid"
    And I expect payment status to be "STATUS_FAILED"
    And I send payment notification with invalid signature
    And new order state must be "open"
    And new transaction state must be "paid"
    # Open homepage so session reset happens on shop domain
    And I am on the homepage

  Scenario: Create stalled payment on notification with signature
    And I select payment option "Santander Installments - Germany Variant 3"
    And I expect payment redirect url to be "none"
    And I expect payment status to be "STATUS_ACCEPTED"
    And I click on CSS locator "#tos"
    When I click on CSS locator "#confirmFormSubmit"
    # wait while payment page/iframe is loaded
    And I wait 10 seconds
    And I send payment notification with signature for "PevrPayeverIntegration.config.clientId" and "PevrPayeverIntegration.config.clientSecret"
    Then new order should be created
    And new order state must be "open"
    And new transaction state must be "paid"
    And I expect payment status to be "STATUS_FAILED"
    And I send stalled payment notification with signature for "PevrPayeverIntegration.config.clientId" and "PevrPayeverIntegration.config.clientSecret" and expect it to fail
    And new order state must be "open"
    And new transaction state must be "paid"
    # Open homepage so session reset happens on shop domain
    And I am on the homepage
