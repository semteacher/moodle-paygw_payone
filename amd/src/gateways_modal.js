/* eslint-disable max-len */
/* eslint-disable no-unused-vars */
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
 * This module is responsible for PayUnity content in the gateways modal.
 *
 * @module     paygw_payunity/gateway_modal
 * @copyright  2022 Wunderbyte Gmbh <info@wunderbyte.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as Repository from './repository';
import Templates from 'core/templates';
import Truncate from 'core/truncate';
import ModalFactory from 'core/modal_factory';
import ModalEvents from 'core/modal_events';
import {get_string as getString} from 'core/str';

/**
 * Creates and shows a modal that contains a placeholder.
 *
 * @returns {Promise<Modal>}
 */
const showModalWithPlaceholder = async() => {
    const modal = await ModalFactory.create({
        body: await Templates.render('paygw_payunity/payunity_button_placeholder', {})
    });
    modal.show();
    return modal;
};

/**
 * Process the payment.
 *
 * @param {string} component Name of the component that the itemId belongs to
 * @param {string} paymentArea The area of the component that the itemId belongs to
 * @param {number} itemId An internal identifier that is used by the component
 * @param {string} description Description of the payment
 * @returns {Promise<string>}
 */
export const process = (component, paymentArea, itemId, description) => {
    return Promise.all([
        showModalWithPlaceholder(),
        Repository.getConfigForJs(component, paymentArea, itemId),
    ])
    .then(([modal, payunityConfig]) => {
        if (payunityConfig.purchaseid === null) {
        return Promise.all([
            modal, payunityConfig
        ]
        );
        } else {
        return Promise.all([
            modal,
            payunityConfig,
            loadSdk(payunityConfig.purchaseid, payunityConfig.environment, payunityConfig.language),
        ]);
         }
    })
    .then(([modal, payunityConfig]) => {

        if (payunityConfig.purchaseid === null) {

            require(['jquery'], function($) {
                require(['core/str'], function(str) {

                    var strings = [
                        {
                            key: 'error',
                            component: 'paygw_payunity'
                        },
                        {
                            key: 'payment_error',
                            component: 'paygw_payunity',
                        },
                        {
                            key: 'proceed',
                            component: 'paygw_payunity',
                        }
                    ];
                    var localStrings = str.get_strings(strings);
                    $.when(localStrings).done(function(localizedEditStrings) {
                        ModalFactory.create({
                            type: ModalFactory.types.CANCEL,
                            title: localizedEditStrings[0],
                            body: localizedEditStrings[1],
                            buttons: {
                                cancel: localizedEditStrings[2],
                            },
                        })
                        .then(function(modal) {
                            var root = modal.getRoot();
                            // eslint-disable-next-line max-nested-callbacks
                            root.on(ModalEvents.cancel, function() {
                                location.href = payunityConfig.rooturl;
                            });
                            modal.show();
                            return '';
                        }).catch({
                            // eslint-disable-next-line no-console
                            // console.log(e);
                        });
                    });

                    });
            });
        } else {
       const form = document.createElement('form');
       const resourcePath = `/v1/checkouts/${payunityConfig.purchaseid}/payment`;
       const url = `${payunityConfig.rooturl}/payment/gateway/payunity/checkout.php?resourcepath=${resourcePath}&itemid=${itemId}&orderid=${payunityConfig.purchaseid}&component=${component}&paymentarea=${paymentArea}`;
       form.setAttribute('action', url);
       form.classList.add('paymentWidgets');
       form.setAttribute('data-brands', "EPS VISA MASTER");
       form.setAttribute('merchantTransactionId', "test123");
        modal.setBody(form);
        return '';
        }
        return '';
    }).then(x => {
        const promise = new Promise(resolve => {
            window.addEventListener('onbeforeunload', (e) => {
                promise.resolve();
            });
        });
        return promise;
    });
};

/**
 * Returns Form
 * @param {string} purchaseid Purchase Id
 * @param {string} environment Environment
 * @param {string} language Session language
 * @returns {Promise}
 */
const loadSdk = (purchaseid, environment, language) => {
    let base = '';
    if (environment === 'sandbox') {
    base = 'https://eu-test.oppwa.com/';
    } else {
    base = 'https://eu-prod.oppwa.com/';
    }
    const sdkUrl = `${base}v1/paymentWidgets.js?checkoutId=${purchaseid}`;

    // Check to see if this file has already been loaded. If so just go straight to the func.
    if (loadSdk.currentlyloaded === sdkUrl) {
        return Promise.resolve();
    }

    if (loadSdk.currentlyloaded) {
        const suspectedScript = document.querySelector(`script[src="${loadSdk.currentlyloaded}"]`);
        if (suspectedScript) {
            suspectedScript.parentNode.removeChild(suspectedScript);
        }
    }

    const script = document.createElement('script');

    return new Promise(resolve => {
        if (script.readyState) {
            script.onreadystatechange = function() {
                if (this.readyState == 'complete' || this.readyState == 'loaded') {
                    this.onreadystatechange = null;
                    resolve();
                }
            };
        } else {
            script.onload = function() {
                resolve();
            };
        }

        script.setAttribute('src', sdkUrl);
        const optionscript = document.createElement('script');
        optionscript.type = 'text/javascript';
        const lang = language;
        const code = `var wpwlOptions = { locale: '${lang}'}`;
        optionscript.appendChild(document.createTextNode(code));
        document.head.appendChild(optionscript);
        document.head.appendChild(script);
        loadSdk.currentlyloaded = sdkUrl;
    });
};

/**
 * Holds the full url
 *
 * @static
 * @type {string}
 */
 loadSdk.currentlyloaded = '';
