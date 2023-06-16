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
    'UpStreamPay_Core/js/upstream-pay-sdk',
    'Magento_Checkout/js/model/full-screen-loader',
    'Magento_Checkout/js/action/place-order',
    'Magento_Ui/js/model/messages',
    'uiLayout',
], function (
    Component,
    messageList,
    customerData,
    $,
    UpStreamPaySdk,
    fullScreenLoader,
    placeOrderAction,
    Messages,
    layout
) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'UpStreamPay_Core/payment/upstream-pay',
            manager: {},
        },

        initialize: function () {
            this._super();
            this.initChildren();
            const self = this;

            this.loadUpStreamPaySdk()
                .then((UpStreamPay) => {
                    self.manager = UpStreamPay.WidgetManager.buildForCredentials({
                        "environment": window.checkoutConfig.payment.UpStreamPay.mode,
                        "entityId": window.checkoutConfig.payment.UpStreamPay.entityId,
                        "apiKey": window.checkoutConfig.payment.UpStreamPay.apiKey,
                    });

                    // Get the session data (order) so we can have a cart context to send to UpStream Pay.
                    this.getSessionData()
                        .done(function (jsonResponse) {
                            const [session] = jsonResponse;
                            self.manager.setPaymentSession(session).then(() => {
                                const widgetPaymentPromise = self.manager.createWidget({
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
         * Place the order in Magento then place the order in UpStream Pay if everything is ok.
         */
        placeOrderUpStreamPay: function () {
            const self = this;

            this.getPlaceOrderDeferredObject()
                .done(
                    function () {
                        //Needed?
                        // self.afterPlaceOrder();
                        console.log('payment is success.');
                        self.manager.submitPayment();
                    }
                ).fail(
                    function () {
                        console.log('it has failed.');
                    }
                );
        },

        /**
         * Get native place order deferrer.
         * (This is Magento native behaviour).
         *
         * @return {*}
         */
        getPlaceOrderDeferredObject: function () {
            return $.when(
                placeOrderAction(this.getData(), this.messageContainer)
            );
        },

        /**
         * Get payment method data
         * (This is Magento native behaviour).
         */
        getData: function () {
            return {
                'method': window.checkoutConfig.payment.UpStreamPay.paymentMethodCode,
                'po_number': null,
                'additional_data': null
            };
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

        /**
         * Initialize child elements
         * (This is Magento native behaviour).
         *
         * @returns {Component} Chainable.
         */
        initChildren: function () {
            this.messageContainer = new Messages();
            this.createMessagesComponent();

            return this;
        },

        /**
         * Create child message renderer component
         * (This is Magento native behaviour).
         *
         * @returns {Component} Chainable.
         */
        createMessagesComponent: function () {

            var messagesComponent = {
                parent: this.name,
                name: this.name + '.messages',
                displayArea: 'messages',
                component: 'Magento_Ui/js/view/messages',
                config: {
                    messageContainer: this.messageContainer
                }
            };

            layout([messagesComponent]);

            return this;
        },
    });
});
