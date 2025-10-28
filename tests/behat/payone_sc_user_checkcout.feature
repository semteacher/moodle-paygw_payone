@paygw @paygw_payone @javascript
Feature: PayUnity basic configuration and useage by user
  In order buy shopping_cart items as a user
  I configure PayOne in background to use company corporative account.

  Background:
    Given the following "users" exist:
      | username | firstname  | lastname    | email                       |
      | user1    | Username1  | Test        | toolgenerator1@example.com  |
      | user2    | Username2  | Test        | toolgenerator2@example.com  |
      | teacher  | Teacher    | Test        | toolgenerator3@example.com  |
      | manager  | Manager    | Test        | toolgenerator4@example.com  |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | user1    | C1     | student        |
      | user2    | C1     | student        |
      | teacher  | C1     | editingteacher |
    And the following "core_payment > payment accounts" exist:
      | name    |
      | PayOne1 |
    And the following "paygw_payone > configuration" exist:
      | account | gateway | enabled |
      | PayOne1 | payone  | 1       |
    And the following "local_shopping_cart > plugin setup" exist:
      | account |
      | PayOne1 |

  @javascript
  Scenario: PayOne: user select two items and pay via card using PayOne
    Given I log in as "user1"
    And Shopping cart has been cleaned for user "user1"
    And Testitem "1" has been put in shopping cart of user "user1"
    And Testitem "2" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    And I should see "Your shopping cart"
    And I should see "Test item 1" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1" "css_element"
    And I should see "10.00 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "Test item 2" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-2" "css_element"
    And I should see "20.30 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-2 .item-price" "css_element"
    ## Price
    And I should see "30.30 EUR" in the ".sc_price_label" "css_element"
    Then I press "Checkout"
    And I should see "payone" in the ".core_payment_gateways_modal" "css_element"
    And I should see "Cost: EUR" in the ".core_payment_fee_breakdown" "css_element"
    And I should see "30.30" in the ".core_payment_fee_breakdown" "css_element"
    And I press "Proceed"
    ## Validate PauOne service page.
    And I wait to be redirected
    And I should see "wunderbyte"
    And I should see "How would you like to pay"
    And I click on "Visa" "text"
    And I wait "1" seconds
    # Important! two identical controls on page! "orderpart" is criaticl to click on!
    And I click on "Proceed to Payment Details" "text" in the ".orderpart .payment-proceed-to-payment" "css_element"
    And I wait "1" seconds
    And I set the field "cardnumber" to "4111 1111 1111 1111"
    And I set the field "cardholdername" to "Behat Test"
    And I set the field "cardexpirationmonth" to "05"
    And I set the field "cardexpirationyear" to "2040"
    And I set the field "cvc" to "123"
    And I wait "1" seconds
    # Important! two identical controls on page! "orderpart" is criaticl to click on!
    And I click on "Pay Securely" "text" in the ".orderpart .button--raised.button--secure" "css_element"
    And I should see "Your payment is accepted"
    And I click on "Continue" "text"
    ## STEPS BELOW DISABLED BECAUSE FAILING CONSTANTLY AT GITHUB ONLY (working OK for manual and local tests)
    ## And I wait to be redirected
    ## Line below - workaround for "An internal error has occurred. Please contact us. resultcode: 5. (press Proceed)"
    ##And I reload the page
    ##And I should see "Payment successful!" in the "#region-main" "css_element"
    ##And I should see "Test item 1" in the ".payment-success ul.list-group" "css_element"
    ##And I should see "Test item 2" in the ".payment-success ul.list-group" "css_element"

  @javascript
  Scenario: PayOne: user select one items and pay twice with late credit added via card using PayOne
    Given I log in as "user1"
    And Shopping cart has been cleaned for user "user1"
    And Testitem "1" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/checkout.php"
    And I open a tab named "checkout2" on the current page
    And I should see "Test item 1" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1" "css_element"
    And I should see "10.00 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    ## Price without credits
    And I should see "10.00 EUR" in the ".sc_price_label" "css_element"
    Then I press "Checkout"
    And I should see "payone" in the ".core_payment_gateways_modal" "css_element"
    And I should see "Cost: EUR" in the ".core_payment_fee_breakdown" "css_element"
    And I should see "10.00" in the ".core_payment_fee_breakdown" "css_element"
    And I press "Proceed"
    ## Validate PauOne service page.
    And I wait to be redirected
    And I should see "wunderbyte"
    And I should see "How would you like to pay"
    And I click on "Visa" "text"
    And I wait "1" seconds
    # Simulate add credits at the last moment
    And the following "local_shopping_cart > user credits" exist:
      | user  | credit | currency |
      | user1 | 4      | EUR      |
    # Return to 1st tab
    ##And I switch to "Your shopping cart" tab
    And I switch to the main tab
    ## To avoid "local_shopping_cart\/noidentifierfound"
    And I reload the page
    And I should see "Use credit: 4.00 EUR"
    And the field "Use credit: 4.00 EUR" matches value "checked"
    And I should see "10.00 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    ## Price with credits applied
    And I should see "6.00 EUR" in the ".sc_price_label" "css_element"
    Then I press "Checkout"
    And I should see "payone" in the ".core_payment_gateways_modal" "css_element"
    And I should see "Cost: EUR" in the ".core_payment_fee_breakdown" "css_element"
    And I should see "6.00" in the ".core_payment_fee_breakdown" "css_element"
    And I press "Proceed"
    ## Validate PauOne service page.
    And I wait to be redirected
    And I should see "wunderbyte"
    And I should see "How would you like to pay"
    And I click on "Visa" "text"
    And I wait "1" seconds
    # Important! two identical controls on page! "orderpart" is criaticl to click on!
    And I click on "Proceed to Payment Details" "text" in the ".orderpart .payment-proceed-to-payment" "css_element"
    And I wait "1" seconds
    And I set the field "cardnumber" to "4111 1111 1111 1111"
    And I set the field "cardholdername" to "Behat Test"
    And I set the field "cardexpirationmonth" to "05"
    And I set the field "cardexpirationyear" to "2040"
    And I set the field "cvc" to "123"
    And I wait "1" seconds
    # Important! two identical controls on page! "orderpart" is criaticl to click on!
    And I click on "Pay Securely" "text" in the ".orderpart .button--raised.button--secure" "css_element"
    And I should see "Your payment is accepted"
    ##And I click on "Continue" "text"
    And I switch to "checkout2" tab
    # Important! two identical controls on page! "orderpart" is criaticl to click on!
    And I click on "Proceed to Payment Details" "text" in the ".orderpart .payment-proceed-to-payment" "css_element"
    And I wait "1" seconds
    And I set the field "cardnumber" to "4111 1111 1111 1111"
    And I set the field "cardholdername" to "Behat Test"
    And I set the field "cardexpirationmonth" to "05"
    And I set the field "cardexpirationyear" to "2040"
    And I set the field "cvc" to "123"
    And I wait "1" seconds
    # Important! two identical controls on page! "orderpart" is criaticl to click on!
    And I click on "Pay Securely" "text" in the ".orderpart .button--raised.button--secure" "css_element"
    And I should see "Your payment is accepted"
    And I click on "Continue" "text"
    ## STEPS BELOW DISABLED BECAUSE FAILING CONSTANTLY AT GITHUB ONLY (working OK for manual and local tests)
    ## And I wait to be redirected
    ## Line below - workaround for "An internal error has occurred. Please contact us. resultcode: 5. (press Proceed)"
    ##And I reload the page
    ##And I should see "Payment successful!" in the "#region-main" "css_element"
    ##And I should see "Test item 1" in the ".payment-success ul.list-group" "css_element"
    ##And I should see "Test item 2" in the ".payment-success ul.list-group" "css_element"

  @javascript
  Scenario: PayOne: user select two items use credits and and pay via card using PayOne
    Given the following "local_shopping_cart > user credits" exist:
      | user  | credit | currency |
      | user1 | 15     | EUR      |
    And I log in as "user1"
    And Shopping cart has been cleaned for user "user1"
    And Testitem "1" has been put in shopping cart of user "user1"
    And Testitem "2" has been put in shopping cart of user "user1"
    And I visit "/local/shopping_cart/checkout.php"
    And I wait until the page is ready
    And I should see "Your shopping cart"
    And I should see "Test item 1" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1" "css_element"
    And I should see "10.00 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-1 .item-price" "css_element"
    And I should see "Test item 2" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-2" "css_element"
    And I should see "20.30 EUR" in the ".checkoutgrid.checkout #item-local_shopping_cart-main-2 .item-price" "css_element"
    ## Price
    And I should see "30.30 EUR" in the ".sc_price_label .sc_initialtotal" "css_element"
    ## Used credit
    And I should see "Use credit: 15.00 EUR" in the ".sc_price_label .sc_credit" "css_element"
    ## Deductible
    And I should see "15.00 EUR" in the ".sc_price_label .sc_deductible" "css_element"
    ## No credit remins
    And I should see "Remaining credit: 0 EUR" in the ".sc_price_label .sc_remainingcredit" "css_element"
    ## Price to pay
    And I should see "15.30 EUR" in the ".sc_price_label .sc_totalprice" "css_element"
    Then I press "Checkout"
    And I should see "payone" in the ".core_payment_gateways_modal" "css_element"
    And I should see "Cost: EUR" in the ".core_payment_fee_breakdown" "css_element"
    And I should see "15.30" in the ".core_payment_fee_breakdown" "css_element"
    And I press "Proceed"
    ## Validate PauOne service page.
    And I wait to be redirected
    And I should see "wunderbyte"
    And I should see "How would you like to pay"
    And I click on "Visa" "text"
    And I wait "1" seconds
    # Important! two identical controls on page! "orderpart" is criaticl to click on!
    And I click on "Proceed to Payment Details" "text" in the ".orderpart .payment-proceed-to-payment" "css_element"
    And I wait "1" seconds
    And I set the field "cardnumber" to "4111 1111 1111 1111"
    And I set the field "cardholdername" to "Behat Test"
    And I set the field "cardexpirationmonth" to "05"
    And I set the field "cardexpirationyear" to "2040"
    And I set the field "cvc" to "123"
    And I wait "1" seconds
    # Important! two identical controls on page! "orderpart" is criaticl to click on!
    And I click on "Pay Securely" "text" in the ".orderpart .button--raised.button--secure" "css_element"
    And I should see "Your payment is accepted"
    And I click on "Continue" "text"
    ## STEPS BELOW DISABLED BECAUSE FAILING CONSTANTLY AT GITHUB ONLY (working OK for manual and local tests)
    And I wait to be redirected
    ## Line below - workaround for "An internal error has occurred. Please contact us. resultcode: 5. (press Proceed)"
    ## And I reload the page
    And I should see "Payment successful!" in the "#region-main" "css_element"
    And I should see "Test item 1" in the ".payment-success ul.list-group" "css_element"
    And I should see "Test item 2" in the ".payment-success ul.list-group" "css_element"
    And I should see "Credits used" in the ".payment-success ul.list-group" "css_element"
    And I should see "Discount: -15.00 EUR" in the ".sc_price_label .sc_discount" "css_element"
    ## verify that all credits has been used
    And I log in as "admin"
    And I visit "/local/shopping_cart/cashier.php"
    And I wait until the page is ready
    And I set the field "Select a user..." to "Username1"
    And I should see "Username1 Test"
    And I click on "Continue" "button"
    And ".cashier-history-items .costcentercredits" "css_element" should not exist
    And I should see "Test item 1" in the "ul.cashier-history-items" "css_element"
    And I should see "Test item 2" in the "ul.cashier-history-items" "css_element"
