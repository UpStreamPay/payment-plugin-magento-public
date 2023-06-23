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
namespace UpStreamPay\Core\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface OrderPaymentSearchResultsInterface
 *
 * @package UpStreamPay\Core\Api\Data
 */
interface OrderPaymentSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get UpStream Pay order payment list.
     *
     * @return OrderPaymentInterface[]
     */
    public function getItems();

    /**
     * Set UpStream Pay order payment list.
     *
     * @param OrderPaymentInterface[] $items
     *
     * @return $this
     */
    public function setItems(array $items);
}
