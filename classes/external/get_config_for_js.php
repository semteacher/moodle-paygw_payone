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
use external_single_structure;
use paygw_payone\task\check_status;
use stdClass;
use DateTime;
use local_shopping_cart\shopping_cart_history;
use paygw_payone\event\payment_added;
use paygw_payone\payone_sdk;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * Class contains a list of webservice functions related to the payone payment gateway.
 *
 * @package    paygw_payone
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_config_for_js extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Component'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area in the component'),
            'itemid' => new external_value(PARAM_INT, 'An identifier for payment area in the component'),
        ]);
    }

    /**
     * Request id.
     *
     * @param string $amount
     * @param string $currency
     * @param string $paymenttype
     * @param string $secret
     * @param string $entityid
     * @param mixed $environment
     * @param mixed $merchanttransactionid
     *
     * @return bool|string
     *
     */
    public static function requestid(string $amount, string $currency, string $paymenttype, string $secret, string $entityid
    , $environment, $merchanttransactionid) {
        if ($environment === 'sandbox') {
            $verify = false;
            $url = "https://eu-test.oppwa.com/v1/checkouts";
        } else {
            $verify = true;
            $url = "https://eu-prod.oppwa.com/v1/checkouts";
        }
        $data = "entityId=" . $entityid . "&amount=" . $amount .
                    "&currency=" . $currency .
                    "&paymentType=" . $paymenttype . "&merchantTransactionId=" . $merchanttransactionid;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Authorization:Bearer ' . $secret]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responsedata = curl_exec($ch);
        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        return $responsedata;
    }

    /**
     * Returns the config values required by the payone JavaScript SDK.
     *
     * @param string $component
     * @param string $paymentarea
     * @param int $itemid
     * @return string[]
     */
    public static function execute(string $component, string $paymentarea, int $itemid): array {
        global $CFG, $DB, $USER, $SESSION;
        self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
        ]);

        if (empty($USER->id)) {
            return [];
        }

        $config = helper::get_gateway_configuration($component, $paymentarea, $itemid, 'payone');
        $payable = helper::get_payable($component, $paymentarea, $itemid);
        $surcharge = helper::get_gateway_surcharge('payone');

        $language = current_language();
        $amount = number_format($payable->get_amount(), 2, '.', '');
        $currency = $payable->get_currency();
        $secret = $config['secret'];
        $entityid = $config['clientid'];
        $root = $CFG->wwwroot;
        $environment = $config['environment'];
        $sandbox = $environment == 'sandbox' ? true : false;

        $string = bin2hex(openssl_random_pseudo_bytes(8));
        $now = new DateTime();
        $timestamp = $now->getTimestamp();

        if ( $component == 'local_shopping_cart' && class_exists('mod_booking\booking')) {
            $cartitems = shopping_cart_history::return_data_via_identifier($itemid, intval($USER->id));

            $cartitemsonlyoptions = array_filter((array) $cartitems,
            function($item) {
                return $item->area == 'option';
            }
            );
            $merchanttransactionid = $itemid . ' ' .  $USER->id;

            foreach ($cartitemsonlyoptions as $item) {
                $explode = explode(' - ', $item->itemid);
                $course = $explode[0];

                $substring = ' K' . $course . ' ' . $item->price;
                $merchanttransactionid .= $substring;

            }
            $pricestring = ' ' . $amount;
            $merchanttransactionid .= $pricestring . ' ' . $timestamp;
            $longmtid = $merchanttransactionid;
            // Payment provider accepts max string length of 40 characters.
            if (strlen($merchanttransactionid) >= 39) {
                $merchanttransactionid = $itemid . ' ' .  $USER->id . ' ' . $amount . ' ' . $timestamp;
            }
            if (strlen($merchanttransactionid) >= 39) {
                $merchanttransactionid = $string . $timestamp;
            }
        } else {
            $merchanttransactionid = $string . $timestamp;
            $longmtid = $merchanttransactionid;
        }

        $sdk = new payone_sdk($config['clientid'], $config['secret'], $config['brandname'], $sandbox );

        $paymentdata = new \stdClass();
        $paymentdata->tid = $merchanttransactionid;
        $paymentdata->amount = helper::get_rounded_cost($payable->get_amount(), $payable->get_currency(), $surcharge);
        $paymentdata->currency = $payable->get_currency();
        $paymentdata->redirecturl = $root . "/payment/gateway/payone/checkout.php?itemid=" . $itemid . "&component=" .
        $component . "&paymentarea=" . $paymentarea;
        $responsedata = $sdk->get_redirect_link_for_payment($paymentdata);

        if ($responsedata->getHostedCheckoutId() !== null) {
            $purchaseid = $responsedata->getMerchantReference();

            // Pepare db item.
            $record = new \stdClass();
            $record->tid = $responsedata->getHostedCheckoutId();
            $record->itemid = $itemid;
            $record->userid = (int) $USER->id;
            $record->price = $amount;
            $record->status = 0;
            $record->timecreated = time();
            $record->timemodified = time();
            $record->merchantref = $longmtid;

            // Check for duplicate.
            if (!$existingrecord = $DB->get_record('paygw_payone_openorders', ['itemid' => $itemid, 'userid' => $USER->id])) {
                $id = $DB->insert_record('paygw_payone_openorders', $record);

                // We trigger the payment_added event.
                $context = context_system::instance();
                $event = payment_added::create([
                    'context' => $context,
                    'userid' => $USER->id,
                    'objectid' => $id,
                    'other' => [
                        'orderid' => $merchanttransactionid,
                    ],
                ]);
                $event->trigger();
            } else {
                // If we already have an entry with the exact same itemid and userid, we actually will use the same merchant id.
                // This will prevent a successful payment and we thus avoid duplicate entries in DB.
                $merchanttransactionid = $existingrecord->tid;
            }
            // Status: 0 pending, 1 canceled, 2 delivered.

            // Create task to check status.
            // We have to check 1 minute before item gets deleted from cache.
            $redirecturl = $responsedata->getRedirectUrl();
            $now = time();
            if (get_config('local_shopping_cart', 'expirationtime') && get_config('local_shopping_cart', 'expirationtime') > 2) {
                $expirationminutes = get_config('local_shopping_cart', 'expirationtime') - 1;
                $nextruntime = strtotime('+' . $expirationminutes . ' min', $now);
            } else {
                // Fallback.
                $nextruntime = strtotime('+30 min', $now);
            }

            $taskdata = new stdClass();
            $taskdata->token = '';
            $taskdata->itemid = $itemid;
            $taskdata->customer = '';
            $taskdata->component = $component;
            $taskdata->paymentarea = $paymentarea;
            $taskdata->tid = $responsedata->getHostedCheckoutId();
            $taskdata->ischeckstatus = false;
            $taskdata->resourcepath = "";
            $taskdata->userid = (int) $USER->id;

            $checkstatustask = new check_status();
            $checkstatustask->set_userid($taskdata->userid);
            $checkstatustask->set_custom_data($taskdata);
            $checkstatustask->set_next_run_time($nextruntime);
            \core\task\manager::reschedule_or_queue_adhoc_task($checkstatustask);
        }

        return [
            'clientid' => $config['clientid'],
            'brandname' => $config['brandname'],
            'cost' => helper::get_rounded_cost($payable->get_amount(), $payable->get_currency(), $surcharge),
            'currency' => $payable->get_currency(),
            'purchaseid' => $purchaseid,
            'rooturl' => $redirecturl,
            'environment' => $environment,
            'language' => $language,
        ];
    }

    /**
     * Returns description of method result value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'clientid' => new external_value(PARAM_TEXT, 'payone client ID'),
            'brandname' => new external_value(PARAM_TEXT, 'Brand name'),
            'cost' => new external_value(PARAM_FLOAT, 'Cost with gateway surcharge'),
            'currency' => new external_value(PARAM_TEXT, 'Currency'),
            'purchaseid' => new external_value(PARAM_TEXT, 'Purchase Id'),
            'rooturl' => new external_value(PARAM_TEXT, 'Moodle Root URI'),
            'environment' => new external_value(PARAM_TEXT, 'Prod or Sandbox'),
            'language' => new external_value(PARAM_TEXT, 'Prod or Sandbox'),
        ]);
    }
}
