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
    'UpStreamPay_Core/js/upstream-pay-sdk'
], function (Component, UpStreamPaySdk) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'UpStreamPay_Core/payment/upstream-pay'
        },

        initialize: function () {
            this._super();

            this.loadUpStreamPaySdk()
                .then((UpStreamPay) => {
                    //MAKE DYNAMIC FROM BACK THROUGH PAYMENT INIT
                    const manager = UpStreamPay.WidgetManager.buildForCredentials({
                        "environment": "sandbox",
                        "entityId": "foo",
                        "apiKey": "foo",
                    });

                    console.log(manager);

                    //TODO GET SESSION FROM PHP => AJAX NEEDED.
                    const session = '';
                    manager.setPaymentSession(session);

                    const widget = manager.createWidget({
                        interface: "PAYMENT",
                        ui:{
                            layout:{name: "ACCORDION",}
                        }
                    });

                    console.log(widget);

                    widget.mount("widget-payment");
                });
        },

        /**
         * Load UpStream Pay SDK.
         *
         */
        loadUpStreamPaySdk: function () {
            return UpStreamPaySdk();
        },
    });
});
