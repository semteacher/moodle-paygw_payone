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
 * payone repository module to encapsulate all of the AJAX requests that can be sent for payone.
 *
 * @module     paygw_payone/confirmpayment
 * @copyright  2022 Georg Maißer <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';

/**
 * Confirm checkout.
 * @param {string} orderid
 * @param {string} itemid
 * @param {string} paymentarea
 * @param {string} component
 * @param {string} resourcePath
 * @param {string} successurl
 */
export const init = (orderid,
                    itemid,
                    paymentarea,
                    component,
                    resourcePath,
                    // eslint-disable-next-line no-unused-vars
                    successurl) => {


    Ajax.call([{
        methodname: "paygw_payone_create_transaction_complete",
        args: {
            component,
            paymentarea,
            itemid,
            tid: orderid,
            token: 0,
            customer: 0,
            ischeckstatus: false,
            resourcepath: resourcePath,
        },
        done: function(data) {


            if (data.success !== true) {

            require(['jquery'], function($) {
                require(['core/str'], function(str) {

                    var strings = [
                        {
                            key: 'error',
                            component: 'paygw_payone'
                        },
                        {
                            key: 'proceed',
                            component: 'paygw_payone',
                        }
                    ];

                    var localStrings = str.get_strings(strings);
                    $.when(localStrings).done(function(localizedEditStrings) {

                        ModalFactory.create({
                            type: ModalFactory.types.CANCEL,
                            title: localizedEditStrings[0],
                            body: data.message,
                            buttons: {
                                cancel: localizedEditStrings[1],
                            },
                        })
                        .then(function(modal) {
                            var root = modal.getRoot();
                            root.on(ModalEvents.cancel, function() {
                                location.href = data.url;
                            });
                            modal.show();
                            return true;
                        }).catch((e) => {
                            // eslint-disable-next-line no-console
                            console.log(e);
                          });

                    });
                });
            });
        } else {
            location.href = data.url;
        }

        },
        fail: function(ex) {
            // eslint-disable-next-line no-console
            console.log(ex);
        },
    }]);

};