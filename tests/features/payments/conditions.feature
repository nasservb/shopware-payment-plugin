@pluginIsEnabled @setupPaymentMethods @javascript @resetSession @payments @6.4
Feature: Conditions

  Scenario Outline: Payment option amount, currency and country restrictions
    Given I am on stub product page
    And I switch to currency <currency>
    And I select product quantity <qty>
    And I press "Add to shopping cart"
    And I wait till I see "Shopping cart"
    And I am on "/checkout/register/"
    And I click on CSS locator ".checkout-main [for=personalGuest]"
    And I select "Mr." from "personalSalutation"
    And I select "<country>" from "billingAddressAddressCountry"
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
    And I wait 1 seconds
    Then I should see the following:
      | title |
      | <po1> |
      | <po2> |
      | <po3> |
    But I should not see the following:
      | title    |
      | <no_po1> |
      | <no_po2> |
      | <no_po3> |

    Examples:
      | qty | country | currency | po1                            | po2                 | po3                 | no_po1                    | no_po2               | no_po3                    |
      | 4   | Germany | EUR      | Santander Installments         | SOFORT Banking      | PayPal              | Santander Invoice         | Santander Factoring  | Santander Installments NO |
      | 1   | Germany | EUR      | Santander DE Invoice           | Santander Factoring | Stripe DirectDebit  | Santander Installments    | Santander Invoice NO | PayEx Invoice             |
      | 1   | Germany | EUR      | Credit Card                    | SOFORT Banking      | Paymill Credit Card | Santander Installments    | Santander Invoice NO | PayEx Invoice             |
      | 1   | Germany | USD      | Credit Card                    | PayPal              | Paymill Credit Card | Santander                 | SOFORT Banking       | PayEx Invoice             |
      | 1   | Norway  | NOK      | Santander Installments Norway  | PayPal              | PayEx Credit Card   | Santander Factoring       | SOFORT Banking       | Santander DE Invoice      |
      | 2   | Norway  | NOK      | Stripe DirectDebit             | Paymill Credit Card | PayEx Credit Card   | PayEx Invoice             | Santander Invoice NO | Santander DE Invoice      |
      | 1   | Denmark | DKK      | Santander Installments Denmark | PayPal              | PayEx Credit Card   | PayEx Invoice             | Santander Invoice NO | Santander DE Invoice      |
      | 1   | Sweden  | SEK      | Santander Installments Sweden  | PayEx Invoice       | PayEx Credit Card   | Santander Installments NO | Santander Invoice NO | Santander DE Invoice      |

  Scenario: Hide payment option on when shipping address differs from billing
    Given I am on stub product page
    And I select product quantity 1
    And I press "Add to shopping cart"
    And I wait till I see "Shopping cart"
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
    And I click on CSS locator ".checkout-main [for=differentShippingAddress]"
    And I select "Mr." from "shippingAddresspersonalSalutation"
    And I select "Germany" from "shippingAddressAddressCountry"
    And I fill in the following:
      | shippingAddresspersonalFirstName | Stub             |
      | shippingAddresspersonalLastName  | Lastname         |
      | shippingAddressAddressStreet     | different street |
      | shippingAddressAddressZipcode    | 10111            |
      | shippingAddressAddressCity       | Berlin           |
    And I press "Continue"
    Then I click on CSS locator ".confirm-checkout-collapse-trigger-label"
    # Modal animation
    And I wait 1 seconds
    Then I should not see the following:
      | title                |
      | Santander DE Invoice |
      | Santander Factoring  |

  Scenario Outline: Hide after failed payment attempt
    Given I am on stub product page
    And I select product quantity <qty>
    And I press "Add to shopping cart"
    And I wait till I see "Shopping cart"
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
    Then I should see "<method_name>"
    And I select payment option "<method_name>"
    And I click on CSS locator "#tos"
    And I expect payment status to be "STATUS_FAILED"
    And I expect payment redirect url to be "failure_url"
    When I click on CSS locator "#confirmFormSubmit"
    And I wait 5 seconds
    And I wait till element exists ".finish-header"
    Then I should see "please change the payment method or try again"
    And I should not see "<method_name>"
    Given I am on stub product page
    And I select product quantity <qty>
    And I press "Add to shopping cart"
    And I wait till I see "Shopping cart"
    And I am on "/checkout/confirm"
    Then I should not see "<method_name>"

    Examples:
      | qty | method_name            |
      | 1   | Santander Factoring DE |
      | 1   | Santander DE Invoice   |
