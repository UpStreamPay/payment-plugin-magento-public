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
 * Interface SubscriptionRetrySearchResultsInterface
 *
 * @package UpStreamPay\Core\Api\Data
 */
interface SubscriptionRetrySearchResultsInterface extends SearchResultsInterface
{
    /**
     * Get UpStream Pay subscription retry list.
     *
     * @return SubscriptionRetryInterface[]
     */
    public function getItems();

    /**
     * Set UpStream Pay subscription retry list.
     *
     * @param SubscriptionRetryInterface[] $items
     *
     * @return $this
     */
    public function setItems(array $items);
}
