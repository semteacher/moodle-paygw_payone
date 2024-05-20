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
 * Contains helper class to work with payone REST API.
 *
 * @package   paygw_payone
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_payone;

use curl;
use OnlinePayments\Sdk\Client;
use OnlinePayments\Sdk\Communicator;
use OnlinePayments\Sdk\CommunicatorConfiguration;
use OnlinePayments\Sdk\DefaultConnection;
use OnlinePayments\Sdk\Domain\AmountOfMoney;
use OnlinePayments\Sdk\Domain\CreateHostedCheckoutRequest;
use OnlinePayments\Sdk\Domain\CreateHostedCheckoutResponse;
use OnlinePayments\Sdk\Domain\GetHostedCheckoutResponse;
use OnlinePayments\Sdk\Domain\HostedCheckoutSpecificInput;
use OnlinePayments\Sdk\Domain\Order;
use OnlinePayments\Sdk\Domain\PaymentReferences;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/payment/gateway/payone/thirdparty/vendor/autoload.php');

/**
 * Helper class to work with payone REST API.
 *
 * @package   paygw_payone
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class payone_sdk {

    /**
     * @var string The base API URL
     */
    private $baseurl;

    /**
     * @var string Client ID
     */
    private $clientid;

    /**
     * @var string payone App secret
     */
    private $secret;

    /**
     * @var string payone Brandname
     */
    private $brandname;

    /**
     * @var string The oath bearer token
     */
    private $token;

    /**
     * @var bool sandbox
     */
    private $sandbox;

    /**
     * helper constructor.
     *
     * @param string $clientid The client id.
     * @param string $secret payone secret.
     * @param string $brandname payone brandname.
     * @param bool $sandbox Whether we are working with the sandbox environment or not.
     */
    public function __construct(string $clientid, string $secret, string $brandname, bool $sandbox) {
        $this->clientid = $clientid;
        $this->secret = $secret;
        $this->brandname = $brandname;
        $this->sandbox = $sandbox;

        $this->baseurl = $sandbox ? 'https://payment.preprod.payone.com' : 'https://payment.payone.com';
    }

    /**
     * Initializes and returns a Client object.
     *
     * @return Client Configured client instance.
     */
    public function return_client_sdk(): Client {
        $connection = new DefaultConnection();
        $communicatorconfiguration = new CommunicatorConfiguration(
            $this->clientid,
            $this->secret,
            $this->baseurl,
            'OnlinePayments'
        );

        $communicator = new Communicator(
            $connection,
            $communicatorconfiguration
        );

        $client = new Client($communicator);

        return $client;
    }

    /**
     * Generates a redirect link for processing a payment using a hosted checkout approach.
     *
     * @param object $data Contains payment information such as transaction id, amount, currency, etc.
     * @return CreateHostedCheckoutResponse Returns an instance of GetHostedCheckoutResponse containing the redirect link.
     */
    public function get_redirect_link_for_payment(object $data): CreateHostedCheckoutResponse {
        $createhostedcheckoutrequest = new CreateHostedCheckoutRequest();

        $refs = new PaymentReferences();
        $refs->setMerchantReference($data->tid);

        $amountofmoney = new AmountOfMoney();
        $amountofmoney->setAmount($data->amount * 100);
        $amountofmoney->setCurrencyCode($data->currency);

        $order = new Order();
        $order->setamountofmoney($amountofmoney);
        $order->setReferences($refs);

        $hostedcheckoutspecificinput = new HostedCheckoutSpecificInput();

        $hostedcheckoutspecificinput->setReturnUrl($data->redirecturl);

        $createhostedcheckoutrequest->setOrder($order);
        $createhostedcheckoutrequest->setHostedCheckoutSpecificInput($hostedcheckoutspecificinput);

        $client = $this->return_client_sdk();

        $createhostedcheckoutresponse =
        $client->merchant($this->brandname)->hostedCheckout()->createHostedCheckout($createhostedcheckoutrequest);

        return $createhostedcheckoutresponse;

    }

    /**
     * Retrieves the status of a hosted checkout process.
     * This method uses the client SDK to fetch the current status of a hosted checkout session
     * by its unique identifier. It is intended to be called with a specific checkout ID, and
     * will return an object containing the response details from the hosted checkout service.
     *
     * @param string $checkoutid The unique identifier for the hosted checkout session.
     * @return GetHostedCheckoutResponse An object that represents the hosted checkout's current status.
     */
    public function check_status(string $checkoutid): GetHostedCheckoutResponse {

        $client = $this->return_client_sdk();
        $hostedcheckoutstatus = $client->merchant($this->brandname)->hostedCheckout()->getHostedCheckout($checkoutid);
        return $hostedcheckoutstatus;
    }

}
