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
 * This module is responsible for PayPal content in the gateways modal.
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
        body: await Templates.render('paygw_payunity/paypal_button_placeholder', {})
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
        // modal.getRoot().on(ModalEvents.hidden, () => {
        //     // Destroy when hidden.
        //     modal.destroy();
        // });

        return Promise.all([
            modal,
            payunityConfig,
            loadSdk(payunityConfig.purchaseid),
        ]);
    })
    .then(([modal, paypalConfig]) => {
        // eslint-disable-next-line max-len
       const form = document.createElement('form');
       form.setAttribute('action', "https://payunity.docs.oppwa.com/tutorials/integration-guide");
       form.classList.add('paymentWidgets');
       form.setAttribute('data-brands', "VISA MASTER AMEX");
        modal.setBody(form);
        //DO PURCHASE?
    }).then(x => new Promise(resolve => setTimeout(() => resolve(x), 100000))
    );
};

/**
 * Returns Form
 * @param {string} purchaseid Purchase Id
 * @returns {Promise}
 */
const loadSdk = (purchaseid) => {
    const sdkUrl = `https://eu-test.oppwa.com/v1/paymentWidgets.js?checkoutId=${purchaseid}`;

    //Check to see if this file has already been loaded. If so just go straight to the func.
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
