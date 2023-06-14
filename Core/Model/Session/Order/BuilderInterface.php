<?php
/**
 * UpStream Pay
 *
 * Copyright (c) 2019-2023 UpStream Pay.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Claranet France <info@fr.clara.net>
 */
namespace UpStreamPay\Core\Model\Session\Order;

use Magento\Quote\Model\Quote;

/**
 * Interface BuilderInterface
 *
 * @package UpStreamPay\Core\Model\Session\Order
 */
interface BuilderInterface
{
    /**
     * Build data needed to create an order so that we can request a session from UpStream Pay.
     *
     * @param Quote $quote
     *
     * @return array
     */
    public function execute(Quote $quote): array;
}
