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
    'jquery'
], function ($) {
    'use strict';

    let dfd = $.Deferred();

    /**
     * Loads the UpStreamPay SDK object
     */
    return function loadUpstreamPayScript() {
        //configuration for loaded UpStream Pay script
        require.config({
            paths: {
                //This url is defined in admin. The .js at of the widget url has been removed in the config provider.
                upStreamPayScript: window.checkoutConfig.payment.UpStreamPay.widgetUrl
            },
            shim: {
                upStreamPayScript: {
                    exports: 'UpStreamPay'
                }
            }
        });

        if (dfd.state() !== 'resolved') {
            require(['upStreamPayScript'], function (upstreamObject) {
                dfd.resolve(upstreamObject);
            });
        }

        return dfd.promise();
    }
});
