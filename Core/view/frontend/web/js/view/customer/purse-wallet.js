/*
 * UpStream Pay
 *
 * Copyright (c) 2023 UpStream Pay.
 * This file is open source and available under the BSD 3 license.
 * See the LICENSE file for more info.
 *
 * Author: Claranet France <info@fr.clara.net>
 */

define([
    'jquery',
    'uiComponent',
    'UpStreamPay_Core/js/upstream-pay-sdk',
    'Magento_Ui/js/model/messageList',
], function(
    $,
    Component,
    UpStreamPaySdk,
    messageList,
) {
    return Component.extend({

        /**
         * Init the wallet widget.
         */
        initialize: function () {
            this._super();
            const self = this;

            this.loadUpStreamPaySdk(this.widgetUrl)
                .then((UpStreamPay) => {
                    self.manager = UpStreamPay.WidgetManager.buildForCredentials({
                        "environment": this.env,
                        "entityId": this.entityId,
                        "apiKey": this.apiKey,
                    });

                    self.manager.setWalletSession(this.walletSession).then(() => {
                        const widgetWalletPromise = self.manager.createWidget({
                            interface: "WALLET",
                        });

                        widgetWalletPromise.then((widgetWallet) => {
                            widgetWallet.mount("widget-wallet");
                        }).catch(() => {
                            messageList.addErrorMessage({
                                message: this.errorMessage
                            });
                        })
                    }).catch(() => {
                        messageList.addErrorMessage({
                            message: this.errorMessage
                        });
                    })
                });
        },

        /**
         * Load UpStream Pay SDK.
         *
         */
        loadUpStreamPaySdk: function (widgetUrl) {
            return UpStreamPaySdk(widgetUrl);
        },
    });
});

