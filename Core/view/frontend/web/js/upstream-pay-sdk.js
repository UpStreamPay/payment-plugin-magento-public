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
    'jquery'
], function ($) {
    'use strict';

    var dfd = $.Deferred();

    /**
     * Loads the PayPal SDK object
     */
    return function loadUpstreamPayScript() {
        //configuration for loaded UpStream Pay script
        require.config({
            paths: {
                // upStreamPayScript: 'https://widget.upstreampay.com/v3-current/UpStreamPay'
                //Temp widget to allow for more testing options.
                upStreamPayScript: 'https://widget.dev.upstreampay.com/latest/UpStreamPay'
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
