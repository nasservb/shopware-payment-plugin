@pluginIsEnabled @setupPaymentMethods @javascript @resetSession @payments
Feature: Finance express widget
    In order to use finance express widget
    As a customer
    I need to be able to place an order

  @6.1
  Scenario: Successful payment
    Given I am on stub product page
    And configure stub product reference
    And I expect payment id to be "some-payment-id-1"
    And I create payment with amount "300"
    And I expect payment status to be "STATUS_ACCEPTED"
    When I send callback by url "/payever/finance-express/success?paymentId=some-payment-id-1"
    And new order should be created
    And new order state must be "open"
    And new transaction state must be "paid"

  @6.4
  Scenario: Successful payment
    Given I am on stub product page
    And configure stub product reference
    And I expect payment id to be "some-payment-id-1"
    And I create payment with amount "300"
    And I expect payment status to be "STATUS_ACCEPTED"
    When I send callback by url "/payever/finance-express/success?paymentId=some-payment-id-1"
    Then I should see "Thank you for your order"
    And new order should be created
    And new order state must be "open"
    And new transaction state must be "paid"

  @6.1
  Scenario: Pending payment
    Given I am on stub product page
    And configure stub product reference
    And I expect payment id to be "some-payment-id-2"
    And I create payment with amount "300"
    And I expect payment status to be "STATUS_IN_PROCESS"
    When I send callback by url "/payever/finance-express/success?paymentId=some-payment-id-2"
    And new order should be created
    And new order state must be "open"
    And new transaction state must be "open"

  @6.4
  Scenario: Pending payment
    Given I am on stub product page
    And configure stub product reference
    And I expect payment id to be "some-payment-id-2"
    And I create payment with amount "300"
    And I expect payment status to be "STATUS_IN_PROCESS"
    When I send callback by url "/payever/finance-express/success?paymentId=some-payment-id-2"
    Then I should see "Thank you for your order"
    And new order should be created
    And new order state must be "open"
    And new transaction state must be "in_progress"

  Scenario: Cancel payment
    Given I am on stub product page
    And configure stub product reference
    And I expect payment id to be "some-payment-id-3"
    When I send callback by url "/payever/finance-express/cancel?paymentId=some-payment-id-3"
    Then I should see "Payment has been canceled"
    And new order should not be created

  Scenario: Failed payment
    Given I am on stub product page
    And configure stub product reference
    And I expect payment id to be "some-payment-id-4"
    When I send callback by url "/payever/finance-express/failure?paymentId=some-payment-id-4"
    Then I should see "Payment has been failed"
    And new order should not be created

  Scenario: Successful payment by notification
    Given configure stub product reference
    And I expect payment id to be "some-payment-id-5"
    And I expect notice_url to be "/payever/finance-express/notice?paymentId=some-payment-id-5"
    And I create payment with amount "300"
    And I expect payment status to be "STATUS_ACCEPTED"
    When I send payment notification for payment "some-payment-id-5"
    And new order should be created
    And new order state must be "open"
    And new transaction state must be "paid"

  Scenario: Invalid amount
    Given I am on stub product page
    And configure stub product reference
    And I expect payment id to be "some-payment-id-6"
    And I create payment with amount "30"
    And I expect payment status to be "STATUS_ACCEPTED"
    When I send callback by url "/payever/finance-express/success?paymentId=some-payment-id-6"
    Then I should see "Payment has been failed"
    And new order should not be created

  Scenario: Skip processing status new on success callback
    Given I am on stub product page
    And configure stub product reference
    And I expect payment id to be "some-payment-id-7"
    And I create payment with amount "300"
    And I expect payment status to be "STATUS_NEW"
    When I send callback by url "/payever/finance-express/success?paymentId=some-payment-id-7"
    And new order should not be created

  Scenario: Skip processing status new on notification
    Given configure stub product reference
    And I expect payment id to be "some-payment-id-8"
    And I expect notice_url to be "/payever/finance-express/notice?paymentId=some-payment-id-8"
    And I create payment with amount "300"
    And I expect payment status to be "STATUS_NEW"
    When I send payment notification for payment "some-payment-id-8"
    And new order should not be created
