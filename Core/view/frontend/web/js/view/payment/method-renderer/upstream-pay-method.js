/*
 * UpStream Pay
 *
 * Copyright (c) 2019-2023 UpStream Pay.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Claranet France <info@fr.clara.net>
 */
define([
    'Magento_Checkout/js/view/payment/default',
    'Magento_Ui/js/model/messageList',
    'Magento_Customer/js/customer-data',
    'jquery',
    'UpStreamPay_Core/js/upstream-pay-sdk'
], function (Component, messageList, customerData, $, UpStreamPaySdk) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'UpStreamPay_Core/payment/upstream-pay'
        },

        initialize: function () {
            this._super();

            this.loadUpStreamPaySdk()
                .then((UpStreamPay) => {
                    const manager = UpStreamPay.WidgetManager.buildForCredentials({
                        "environment": window.checkoutConfig.payment.UpStreamPay.mode,
                        "entityId": window.checkoutConfig.payment.UpStreamPay.entityId,
                        "apiKey": window.checkoutConfig.payment.UpStreamPay.apiKey,
                    });

                    // Get the session data (order) so we can have a cart context to send to UpStream Pay.
                    this.getSessionData()
                        .done(function (jsonResponse) {
                            const [session] = jsonResponse;
                            manager.setPaymentSession(session).then(() => {
                                const widgetPaymentPromise = manager.createWidget({
                                    interface: "PAYMENT",
                                    ui: {
                                        layout: {name: "ACCORDION",}
                                    }
                                });


                                widgetPaymentPromise.then((widgetPayment) => {
                                    widgetPayment.mount("widget-payment");
                                    self.manager.subscribe(event => {
                                        if (event.code === 'CHECKOUT_PAYMENT_FULFILLED_CHANGES') {
                                            if (event.payload.isFulfilled) {
                                                console.log('PAYMENT OK');
                                                document.getElementById('submit-ups-payment').removeAttribute('disabled');
                                            } else {
                                                console.log('PAYMENT KO');
                                                document.getElementById('submit-ups-payment').setAttribute('disabled', true);
                                            }
                                        }
                                    });
                                })
                            })
                        })
                        .fail(function () {
                            messageList.addErrorMessage({
                                message: window.checkoutConfig.payment.UpStreamPay.errorMessage
                            });
                        });
                });
        },

        /**
         * Load UpStream Pay SDK.
         *
         */
        loadUpStreamPaySdk: function () {
            return UpStreamPaySdk();
        },

        /**
         * Retrieve session data (order) to send to UpStream Pay, so we can get all the payment methods avaible for
         * the cart.
         *
         * @returns {Promise}
         */
        getSessionData: function () {
            return $.ajax({
                url: '/rest/V1/upstreampay/session', // Replace with your actual API endpoint URL
                type: 'GET',
                dataType: 'json'
            });
        },
    });
});
