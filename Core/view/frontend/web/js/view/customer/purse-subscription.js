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
    'ko',
    'uiComponent',
    'Magento_Ui/js/modal/confirm'
], function ($, ko, Component, confirm) {
    'use strict';

    return Component.extend({
        initialize: function () {
            this._super();
            let self = this;
            $('.subscription-cancel').click(function (event) {
                let productName = event.currentTarget.getAttribute("data-product-name");
                confirm({
                    title: $.mage.__(productName),
                    content: $.mage.__('Are you sure you want to cancel the subscription ?'),
                    actions: {
                        confirm: function () {
                            let subId = event.currentTarget.getAttribute("data-id");
                            $.post(
                                self.cancelUrl,
                                { subscription_id: subId },
                                function () {
                                    window.location.reload();
                                }
                            );
                        },
                        cancel: function () {

                        }
                    },
                    buttons: [{
                        text: $.mage.__('Cancel'),
                        class: 'action-secondary action-dismiss',
                        click: function (event) {
                            this.closeModal(event);
                        }
                    }, {
                        text: $.mage.__('OK'),
                        class: 'action primary action-accept',
                        click: function (event) {
                            this.closeModal(event, true);
                        }
                    }]
                });
            })
        }
    });
});
