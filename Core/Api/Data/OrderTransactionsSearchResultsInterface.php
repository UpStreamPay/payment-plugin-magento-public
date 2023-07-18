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
namespace UpStreamPay\Core\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * Interface OrderTransactionsSearchResultsInterface
 *
 * @package UpStreamPay\Core\Api\Data
 */
interface OrderTransactionsSearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get order transactions list.
     *
     * @return OrderTransactionsInterface[]
     */
    public function getItems();

    /**
     * Set order transactions list.
     *
     * @param OrderTransactionsInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
