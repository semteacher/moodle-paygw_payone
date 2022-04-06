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
 * PayPal repository module to encapsulate all of the AJAX requests that can be sent for PayPal.
 *
 * @module     paygw_payunity/confirmpayment
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';

/**
 * Confirm checkout.
 * @param {string} checkoutid
 */
export const init = (orderid,
                    itemid,
                    paymentarea,
                    component,
                    resourcePath) => {

    // eslint-disable-next-line no-console
    console.log('orderid', orderid);
    // eslint-disable-next-line no-console
    console.log('checkoutid', orderid, itemid, paymentarea, component, resourcePath);

    Ajax.call([{
        methodname: "paygw_payunity_create_transaction_complete",
        args: {
            component,
            paymentarea,
            orderid,
            itemid,
            resourcePath
        },
        done: function(data) {

            // eslint-disable-next-line no-console
            console.log(data);
        },
        fail: function(ex) {
            // eslint-disable-next-line no-console
            console.log("ex:" + ex);
        },
    }]);

};