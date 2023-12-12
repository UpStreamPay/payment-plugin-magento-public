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
    return function loadUpstreamPayScript(widgetUrl) {
        if (widgetUrl === undefined) {
            widgetUrl = window.checkoutConfig.payment.UpStreamPay.widgetUrl
        }

        //configuration for loaded UpStream Pay script
        require.config({
            paths: {
                //This url is defined in admin. The .js at the end of the widget url has been removed in the config
                // provider.
                upStreamPayScript: widgetUrl
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
