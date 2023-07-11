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
 * Interface AddressBuilderInterface
 *
 * @package UpStreamPay\Core\Model\Session\Order
 */
interface AddressBuilderInterface extends BuilderInterface
{
    public const BILLING_ADDRESS = 'billing';
    public const SHIPPING_ADDRESS = 'shipping';

    /**
     * Build the Address (shipping or billing) data for the UpStream Pay order.
     *
     * @param CartInterface $quote
     * @param string $addressType
     *
     * @return array
     *
     * @see UpStream Pay documentation regarding all the fields & format.
     */
    public function execute(CartInterface $quote, string $addressType = self::BILLING_ADDRESS): array;
}
