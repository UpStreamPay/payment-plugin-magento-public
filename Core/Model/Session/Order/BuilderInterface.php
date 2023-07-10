<?php
/**
 * UpStream Pay
 *
 * Copyright (c) 2023 UpStream Pay.
 * This file is open source and available under the BSD 3 license.
 * See the LICENSE file for more info.
 *
 * Author: Claranet France <info@fr.clara.net>
 */
namespace UpStreamPay\Core\Model\Session\Order;

use Magento\Quote\Api\Data\CartInterface;

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
     * @param CartInterface $quote
     *
     * @return array
     *
     * @see UpStream Pay documentation regarding all the fields & format.
     */
    public function execute(CartInterface $quote): array;
}
