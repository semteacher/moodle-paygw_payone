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
 * This class contains a list of webservice functions related to the payone payment gateway.
 *
 * @package    paygw_payone
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace paygw_payone\external;

use context_system;
use core_payment\helper;
use external_api;
use external_function_parameters;
use external_value;
use core_payment\helper as payment_helper;
use paygw_payone\event\delivery_error;
use paygw_payone\event\payment_completed;
use paygw_payone\event\payment_error;
use paygw_payone\event\payment_successful;
use paygw_payone\payone_helper;
use local_shopping_cart\interfaces\interface_transaction_complete;
use paygw_payone\interfaces\interface_transaction_complete as pu_interface_transaction_complete;
use paygw_payone\payone_sdk;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

if (!interface_exists(interface_transaction_complete::class)) {
    class_alias(pu_interface_transaction_complete::class, interface_transaction_complete::class);
}

/**
 * Class contains a list of webservice functions related to the payone payment gateway.
 *
 * @package    paygw_payone
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transaction_complete extends external_api implements interface_transaction_complete {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'The component name'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area in the component'),
            'itemid' => new external_value(PARAM_INT, 'The item id in the context of the component area'),
            'tid' => new external_value(PARAM_TEXT, 'unique transaction id'),
            'token' => new external_value(PARAM_RAW, 'Purchase token', VALUE_DEFAULT, ''),
            'customer' => new external_value(PARAM_RAW, 'Customer Id', VALUE_DEFAULT, ''),
            'ischeckstatus' => new external_value(PARAM_BOOL, 'If initial purchase or cron execution', VALUE_DEFAULT, false),
            'resourcepath' => new external_value(PARAM_TEXT, 'The order id coming back from payone', VALUE_DEFAULT, ''),
            'userid' => new external_value(PARAM_INT, 'User ID', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Perform what needs to be done when a transaction is reported to be complete.
     * This function does not take cost as a parameter as we cannot rely on any provided value.
     *
     * @param string $component Name of the component that the itemid belongs to
     * @param string $paymentarea payment area
     * @param int $itemid An internal identifier that is used by the component
     * @param string $tid unique transaction id
     * @param string $token
     * @param string $customer
     * @param bool $ischeckstatus
     * @param string $resourcepath
     * @param int $userid
     * @return array
     */
    public static function execute(string $component, string $paymentarea, int $itemid, string $tid, string $token = '0',
     string $customer = '0', bool $ischeckstatus = false, string $resourcepath = '', int $userid = 0): array {
        global $USER, $DB, $CFG;

        $success = false;
        $setfailed = false;
        $message = '';
        $successurl = helper::get_success_url($component, $paymentarea, $itemid)->__toString();
        $serverurl = $CFG->wwwroot;

        if (empty($userid)) {
            $userid = $USER->id;
            // Fallback: If it's the system user 0, we need to get the REAL user from openorders table!
            if (empty($userid)) {
                if (!$userid = $DB->get_field('paygw_payone_openorders', 'userid', ['itemid' => $itemid])) {
                    // We need a hard stop. If for any reason we can't find out the userid, we log it and stop.
                    // We trigger the payment_error event.
                    $context = context_system::instance();
                    $event = payment_error::create([
                        'context' => $context,
                        'userid' => $userid,
                        'other' => [
                                'message' => 'nouseridintransactioncomplete',
                                'orderid' => $tid,
                                'itemid' => $itemid,
                                'component' => $component,
                                'paymentarea' => $paymentarea]]);
                    $event->trigger();
                    throw new \moodle_exception('nouseridintransactioncomplete', 'paygw_payone');
                }
            }
        }

        // We need to prevent duplicates, so check if the payment already exists!
        if ($DB->get_records('payments', [
            'component' => 'local_shopping_cart',
            'itemid' => $itemid,
        ])) {
            return [
                'url' => $successurl ?? $serverurl,
                'success' => true,
                'message' => get_string('payment_alreadyexists', 'paygw_payone'),
            ];
        }

        self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
            'tid' => $tid,
            'token' => $token,
            'customer' => $customer,
            'ischeckstatus' => $ischeckstatus,
            'resourcepath' => $resourcepath,
            'userid' => $userid,
        ]);

        $config = (object)helper::get_gateway_configuration($component, $paymentarea, $itemid, 'payone');
        $sandbox = $config->environment == 'sandbox';

        $payable = payment_helper::get_payable($component, $paymentarea, $itemid);
        $currency = $payable->get_currency();

        // Add surcharge if there is any.
        $surcharge = helper::get_gateway_surcharge('payone');
        $amount = helper::get_rounded_cost($payable->get_amount(), $currency, $surcharge);

        $sdk = new payone_sdk($config->clientid, $config->secret, $config->brandname, $sandbox );
        $orderdetails = $sdk->check_status($tid);
        $statusorder = $orderdetails->getStatus();
        if ($orderdetails && $statusorder == 'PAYMENT_CREATED') {
            $status = '';
            $url = $serverurl;
            // SANDBOX OR PROD.
            $statuspayment = $orderdetails->getCreatedPaymentOutput()->getPaymentStatusCategory();
            if ($sandbox == true) {
                if ($statuspayment == 'SUCCESSFUL') {
                    // Approved.
                    $status = 'success';
                    $message = get_string('payment_successful', 'paygw_payone');
                } else if ($statuspayment == 'REJECTED' || $orderdetails->getCreatedPaymentOutput()->getPayment() == null) {
                    $status = false;
                    $setfailed = true;
                } else {
                    // Not Approved.
                    $status = false;
                }
            } else {
                if ($statuspayment == 'SUCCESSFUL') {
                    // Approved.
                    $status = 'success';
                    $message = get_string('payment_successful', 'paygw_payone');
                } else if ($statuspayment == 'REJECTED') {
                    $status = false;
                    $setfailed = true;
                } else {
                    // Not Approved.
                    $status = false;
                }
            }

            if ($status === 'success') {
                $url = $successurl;
                // Get item from response.
                $item['amount'] = $orderdetails->getCreatedPaymentOutput()
                    ->getPayment()->getPaymentOutput()->getAmountOfMoney()->getAmount() / 100;
                $item['currency'] = $orderdetails->getCreatedPaymentOutput()
                    ->getPayment()->getPaymentOutput()->getAmountOfMoney()->getCurrencyCode();

                /* The amount from payable might not take into account credit payment if cache was deleted.
                Therefore, we check the amount from openorders table to make sure we don't abort a successful payment
                because of an amount mismatch. */
                if ($item['amount'] != $amount) {
                    $pricefromopenorders = (float) $DB->get_field('paygw_payone_openorders', 'price', ['tid' => $tid]);
                    if (!empty($pricefromopenorders)) {
                        $amount = helper::get_rounded_cost($pricefromopenorders, $currency, $surcharge);
                    }
                }

                if ($item['amount'] == $amount && $item['currency'] == $currency) {
                    $success = true;

                    try {
                        $paymentid = payment_helper::save_payment($payable->get_account_id(), $component, $paymentarea,
                            $itemid, (int) $userid, $amount, $currency, 'payone');

                        // Store payone extra information.
                        $record = new \stdClass();
                        $record->paymentid = $paymentid;
                        $record->pu_orderid = $tid;

                        $brandcode = $orderdetails->getCreatedPaymentOutput()
                            ->getPayment()->getPaymentOutput()->getRedirectPaymentMethodSpecificOutput()->getPaymentProductId();

                        // Store Brand in DB.
                        if (get_string_manager()->string_exists($brandcode, 'paygw_payone')) {
                            $record->paymentbrand = get_string($brandcode, 'paygw_payone');
                        } else {
                            $record->paymentbrand = get_string('unknownbrand', 'paygw_payone');
                        }

                        // Store original value.
                        $record->pboriginal = (string) $brandcode;

                        $DB->insert_record('paygw_payone', $record);

                        // Set status in open_orders to complete.
                        if ($existingrecord = $DB->get_record('paygw_payone_openorders',
                        ['tid' => $tid])) {
                            $existingrecord->status = 3;
                            $DB->update_record('paygw_payone_openorders', $existingrecord);

                            // We trigger the payment_completed event.
                            $context = context_system::instance();
                            $event = payment_completed::create([
                                'context' => $context,
                                'userid' => $userid,
                                'other' => [
                                    'orderid' => $tid,
                                ],
                            ]);
                            $event->trigger();
                        }

                        // We trigger the payment_successful event.
                        $context = context_system::instance();
                        $event = payment_successful::create([
                            'context' => $context,
                            'userid' => $userid,
                            'other' => [
                                'message' => $message,
                                'orderid' => $tid,
                            ]]);
                        $event->trigger();

                        // If the delivery was not successful, we trigger an event.
                        if (!payment_helper::deliver_order($component, $paymentarea, $itemid, $paymentid, (int) $userid)) {

                            $context = context_system::instance();
                            $event = delivery_error::create([
                                'context' => $context,
                                'userid' => $userid,
                                'other' => [
                                    'message' => $message,
                                    'orderid' => $tid,
                                ]]);
                            $event->trigger();
                        }
                    } catch (\Exception $e) {
                        debugging('Exception while trying to process payment: ' . $e->getMessage(), DEBUG_DEVELOPER);
                        $success = false;
                        $message = get_string('internalerror', 'paygw_payone')
                        . " resultcode: " . $orderdetails->getCreatedPaymentOutput()
                            ->getPayment()->getStatusOutput()->getStatusCode() ?? ' noresultcode';
                    }
                } else {
                    $success = false;
                    $message = get_string('amountmismatch', 'paygw_payone')
                    . " resultcode: " . $orderdetails->getCreatedPaymentOutput()
                        ->getPayment()->getStatusOutput()->getStatusCode() ?? ' noresultcode';
                }

            } else {
                $success = false;
                // Get the payment output only once to avoid multiple calls.
                $paymentoutput = $orderdetails->getCreatedPaymentOutput();
                $payment = $paymentoutput ? $paymentoutput->getPayment() : null;
                $statuscode = $payment ? $payment->getStatusOutput()->getStatusCode() : 'noresultcode';

                $message = get_string('paymentnotcleared', 'paygw_payone') . " resultcode: " . $statuscode;

            }

        } else {
            // Could not capture authorization!
            $success = false;
            $message = get_string('cannotfetchorderdetails', 'paygw_payone') . " code: " .
                $statusorder ?? "nocodefound";
        }

        // If there is no success, we trigger this event.
        if (!$success) {

            if ($setfailed) {
                if ($existingrecord = $DB->get_record('paygw_payone_openorders',
                ['tid' => $tid])) {
                    $existingrecord->status = 2;
                    $DB->update_record('paygw_payone_openorders', $existingrecord);
                }
            }
            // We trigger the payment_error event.
            $context = context_system::instance();
            $event = payment_error::create([
                'context' => $context,
                'userid' => $userid,
                'other' => [
                        'message' => $message,
                        'orderid' => $tid,
                        'itemid' => $itemid,
                        'component' => $component,
                        'paymentarea' => $paymentarea]]);
            $event->trigger();
        }

        return [
            'url' => $url ?? '',
            'success' => $success,
            'message' => $message,
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_function_parameters
     */
    public static function execute_returns(): external_function_parameters {
        return new external_function_parameters([
            'url' => new external_value(PARAM_URL, 'Redirect URL.'),
            'success' => new external_value(PARAM_BOOL, 'Whether everything was successful or not.'),
            'message' => new external_value(PARAM_RAW, 'Message (usually the error message).'),
        ]);
    }
}
