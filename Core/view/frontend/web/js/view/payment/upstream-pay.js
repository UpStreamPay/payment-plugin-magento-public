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
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list',
], function (Component, rendererList) {
    'use strict';

    rendererList.push(
        {
            type: 'upstream_pay',
            component: 'UpStreamPay_Core/js/view/payment/method-renderer/upstream-pay-method'
        }
    );

    return Component.extend({});
});
