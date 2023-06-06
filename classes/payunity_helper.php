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
 * Contains helper class to work with PayUnity REST API.
 *
 * @package    core_payment
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_payunity;

use curl;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

class payunity_helper {

    /**
     * @var string The base API URL
     */
    private $baseurl;

    /**
     * @var string Client ID
     */
    private $clientid;

    /**
     * @var string PayUnity App secret
     */
    private $secret;

    /**
     * @var string The oath bearer token
     */
    private $token;

    /**
     * @var boolean sandbox
     */
    private $sandbox;

    /**
     * helper constructor.
     *
     * @param string $clientid The client id.
     * @param string $secret PayUnity secret.
     * @param bool $sandbox Whether we are working with the sandbox environment or not.
     */
    public function __construct(string $clientid, string $secret, bool $sandbox) {
        $this->clientid = $clientid;
        $this->secret = $secret;
        $this->sandbox = $sandbox;
        $this->baseurl = $sandbox ? 'https://eu-test.oppwa.com' : 'https://eu-prod.oppwa.com';
    }

    public function get_order_details(string $resourcepath) {

        $url = $this->baseurl . $resourcepath;
        $url .= "?entityId={$this->clientid}";

        if ($this->sandbox == true) {
            $verify = false;
        } else {
            $verify = true;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        "Authorization:Bearer {$this->secret}"));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responsedata = curl_exec($ch);
        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        return json_decode($responsedata);
    }

    public function get_transaction_record(string $merchanttransactionid) {
        $url = $this->baseurl . "/v1/query";
        $url .= "?entityId={$this->clientid}";
        $url .= "&merchantTransactionId=" . urlencode($merchanttransactionid);

        if ($this->sandbox == true) {
            $verify = false;
        } else {
            $verify = true;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        "Authorization:Bearer {$this->secret}"));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responsedata = curl_exec($ch);
        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        return json_decode($responsedata);
    }


    public function get_transaction_record_exetrnal_id(string $purchaseid) {
        $url = $this->baseurl . "/v1/query/" . $purchaseid;
        $url .= "?entityId={$this->clientid}";

        if ($this->sandbox == true) {
            $verify = false;
        } else {
            $verify = true;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                        "Authorization:Bearer {$this->secret}"));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responsedata = curl_exec($ch);
        if (curl_errno($ch)) {
            return curl_error($ch);
        }
        curl_close($ch);
        return json_decode($responsedata);
    }
}
