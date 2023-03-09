@pluginIsEnabled @setupPaymentMethods @javascript @resetSession @payments @gte6.4.15
Feature: Payment

  Background:
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

  Scenario: Check authorized status
    Then new order state must be "open"
    And new transaction state must be "authorized"
