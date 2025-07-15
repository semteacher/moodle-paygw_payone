<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Testing checkout in payment gateway paygw_payone
 *
 * @package    paygw_payone
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_payone;

use local_shopping_cart\shopping_cart;
use local_shopping_cart\local\cartstore;
use OnlinePayments\Sdk\Domain\AmountOfMoney;
use OnlinePayments\Sdk\Domain\CardInfo;
use OnlinePayments\Sdk\Domain\CreatedPaymentOutput;
use OnlinePayments\Sdk\Domain\CreateHostedCheckoutResponse;
use OnlinePayments\Sdk\Domain\GetHostedCheckoutResponse;
use OnlinePayments\Sdk\Domain\PaymentOutput;
use OnlinePayments\Sdk\Domain\PaymentResponse;
use OnlinePayments\Sdk\Domain\PaymentStatusOutput;
use OnlinePayments\Sdk\Domain\RedirectPaymentMethodSpecificOutput;
use paygw_payone\external\get_config_for_js;
use paygw_payone\external\transaction_complete;
use stdClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/payment/gateway/payone/thirdparty/vendor/autoload.php');

/**
 * Testing checkout in payment gateway paygw_payone
 *
 * @package    paygw_payone
 * @category   test
 * @copyright  2024 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @runTestsInSeparateProcesses
 */
final class transaction_complete_test extends \advanced_testcase {

    /** @var \core_payment\account account */
    private $account;

    /**
     * Setup function.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        set_config('country', 'AT');
        $generator = $this->getDataGenerator()->get_plugin_generator('core_payment');
        $this->account = $generator->create_payment_account(['name' => 'PayOne1']);

        $record = new stdClass();
        $record->accountid = $this->account->get('id');
        $record->gateway = 'payone';
        $record->enabled = 1;
        $record->timecreated = time();
        $record->timemodified = time();

        $config = new stdClass();
        $config->environment = 'sandbox';
        // Load the credentials from Github.
        $config->brandname = getenv('BRANDNAME') ?: 'fakename';
        $config->clientid = getenv('CLIENTID') ?: 'fakeclientid';
        $config->secret = getenv('PAYONE_SECRET') ?: 'fakesecret';

        $record->config = json_encode($config);

        $accountgateway1 = \core_payment\helper::save_payment_gateway($record);

        // Mock responsedata from payment gateway.
        $responsedata = $this->createMock(CreateHostedCheckoutResponse::class);
        $responsedata->method('getHostedCheckoutId')
            ->willReturnCallback(function () {
                return str_pad(rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
            });
        $responsedata->method('getRedirectUrl')->willReturn('https://payment.preprod.payone.com/hostedcheckout/PaymentMethods/');

        $amoutofmoney = $this->createMock(AmountOfMoney::class);
        $amoutofmoney->method('getAmount')->willReturn(4410);
        $amoutofmoney->method('getCurrencyCode')->willReturn('EUR');

        $statusoutput = $this->createMock(PaymentStatusOutput::class);
        $statusoutput->method('getStatusCode')->willReturn('800.100.100');

        $redirectspecificoutput = $this->createMock(RedirectPaymentMethodSpecificOutput::class);
        $redirectspecificoutput->method('getPaymentProductId')->willReturn('VC');

        // Mock orderdetails.
        $paymentoutput = $this->createMock(PaymentOutput::class);
        $paymentoutput->method('getAmountOfMoney')->willReturn($amoutofmoney);
        $paymentoutput->method('getRedirectPaymentMethodSpecificOutput')->willReturn($redirectspecificoutput);

        $cardpaymentmethod = $this->createMock(CardInfo::class);
        $cardpaymentmethod->method('getPaymentProductId')->willReturn('test_product_id');

        $paymentresponse = $this->createMock(PaymentResponse::class);
        $paymentresponse->method('getPaymentOutput')->willReturn($paymentoutput);
        $paymentresponse->method('getStatusOutput')->willReturn($statusoutput);
        $paymentresponse->method('getStatus')->willReturn('CAPTURED');

        $createdpaymentoutput = $this->createMock(CreatedPaymentOutput::class);
        $createdpaymentoutput->method('getPayment')->willReturn($paymentresponse);
        $createdpaymentoutput->method('getPaymentStatusCategory')->willReturn('SUCCESSFUL');

        $orderdetails = $this->createMock(GetHostedCheckoutResponse::class);
        $orderdetails->method('getStatus')->willReturn('PAYMENT_CREATED');
        $orderdetails->method('getCreatedPaymentOutput')->willReturn($createdpaymentoutput);

        // Create a PHPUnit mock for payone_sdk.
        $sdkmock = $this->getMockBuilder(payone_sdk::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get_redirect_link_for_payment', 'check_status'])
            ->getMock();

        // Define mock behavior.
        $sdkmock->method('get_redirect_link_for_payment')
            ->willReturn($responsedata);

        // Define mock behavior.
        $sdkmock->method('check_status')
            ->willReturn($orderdetails);

        // Override the factory to return our mock.
        payone_sdk::$factory = function () use ($sdkmock) {
            return $sdkmock;
        };
    }

    /**
     * Test transaction complete process
     *
     * @covers \paygw_payone\gateway
     * @covers \local_shopping_cart\payment\service_provider::get_payable()
     * @throws \coding_exception
     */
    public function test_successfull_checkout(): void {
        global $DB;

        // Create users.
        $student1 = $this->getDataGenerator()->create_user();
        $this->setUser($student1);
        // Validate payment account if it has a config.
        $record1 = $DB->get_record('payment_accounts', ['id' => $this->account->get('id')]);
        $this->assertEquals('PayOne1', $record1->name);
        $this->assertCount(1, $DB->get_records('payment_gateways', ['accountid' => $this->account->get('id')]));

        // Set local_shopping_cart to use the payment account.
        set_config('accountid', $this->account->get('id'), 'local_shopping_cart');

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'testitem',
            1,
            $student1->id
        );

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'testitem',
            2,
            $student1->id
        );

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'testitem',
            3,
            $student1->id
        );

        // With this code, we instantiate the checkout for this user.
        $cartstore = cartstore::instance($student1->id);
        $data = $cartstore->get_localized_data();
        $cartstore->get_expanded_checkout_data($data);
        $res = get_config_for_js::execute('local_shopping_cart', 'main', $data['identifier']);

        $historyrecords = $DB->get_records('local_shopping_cart_history');
        $this->assertEquals(3, count($historyrecords));

        $tid = (int)$DB->get_field('paygw_payone_openorders', 'tid', ['userid' => $student1->id, 'itemid' => $data['identifier']]);
        $this->assertIsInt($tid, 'The value of $tid should be an integer.');

        $result = transaction_complete::execute(
            'local_shopping_cart',
            '',
            $data['identifier'],
            $tid
        );

        $this->assertEquals($result["success"], true, 'Should be successful');
    }

    /**
     * Test transaction complete process
     *
     * @covers \paygw_payone\gateway
     * @covers \local_shopping_cart\payment\service_provider::get_payable()
     * @throws \coding_exception
     */
    public function test_switch_user_checkout(): void {
        global $DB;

        // Create users.
        $student1 = $this->getDataGenerator()->create_user();
        $this->setUser($student1);
        // Validate payment account if it has a config.
        $record1 = $DB->get_record('payment_accounts', ['id' => $this->account->get('id')]);
        $this->assertEquals('PayOne1', $record1->name);
        $this->assertCount(1, $DB->get_records('payment_gateways', ['accountid' => $this->account->get('id')]));

        // Set local_shopping_cart to use the payment account.
        set_config('accountid', $this->account->get('id'), 'local_shopping_cart');

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'testitem',
            1,
            $student1->id
        );

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'testitem',
            2,
            $student1->id
        );

        shopping_cart::add_item_to_cart(
            'local_shopping_cart',
            'testitem',
            3,
            $student1->id
        );

        // With this code, we instantiate the checkout for this user.
        $cartstore = cartstore::instance($student1->id);
        $data = $cartstore->get_localized_data();
        $cartstore->get_expanded_checkout_data($data);
        $res = get_config_for_js::execute('local_shopping_cart', 'main', $data['identifier']);

        $historyrecords = $DB->get_records('local_shopping_cart_history');
        $this->assertEquals(3, count($historyrecords));

        $tid = (int)$DB->get_field('paygw_payone_openorders', 'tid', ['userid' => $student1->id, 'itemid' => $data['identifier']]);
        $this->assertIsInt($tid, 'The value of $tid should be an integer.');

        $this->setAdminUser();

        $result = transaction_complete::execute(
            'local_shopping_cart',
            '',
            $data['identifier'],
            $tid
        );
        $this->assertEquals($result["success"], false, 'because of the user switch, this is not successful');

        // Now we execute the same transaction complete as admin, for the real user.
        $result = transaction_complete::execute(
            'local_shopping_cart',
            '',
            $data['identifier'],
            $tid,
            0,
            0,
            true,
            '',
            $student1->id,
        );

        $this->assertEquals(
            $result["success"],
            true,
            'We do it for another user, but declared (like in adhoc task or check status button'
        );
    }
}
