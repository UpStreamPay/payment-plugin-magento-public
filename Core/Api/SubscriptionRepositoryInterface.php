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

namespace UpStreamPay\Core\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use UpStreamPay\Core\Api\Data\SubscriptionInterface;
use UpStreamPay\Core\Api\Data\SubscriptionSearchResultsInterface;

/**
 * Interface SubscriptionRepositoryInterface
 *
 * @package UpStreamPay\Core\Api
 */
interface SubscriptionRepositoryInterface
{
    /**
     * Save UpStream Pay Subscription.
     *
     * @param SubscriptionInterface $subscription
     * @return SubscriptionInterface
     * @throws LocalizedException
     */
    public function save(SubscriptionInterface $subscription): SubscriptionInterface;

    /**
     * @param int $entityId
     *
     * @return SubscriptionInterface
     * @throws LocalizedException
     */
    public function getById(int $entityId): SubscriptionInterface;

    /**
     * @param string $identifier
     *
     * @return SubscriptionInterface
     * @throws LocalizedException
     */
    public function getBySubscriptionIdentifier(string $identifier): SubscriptionInterface;

    /**
     * Retrieve UpStream Pay order payment matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return SubscriptionSearchResultsInterface
     * @throws LocalizedException
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SubscriptionSearchResultsInterface;

    /**
     * @param string $sku
     * @param int $orderId
     * @return SubscriptionInterface
     */
    public function getByProductSkuAndOrderId(string $sku, int $orderId): SubscriptionInterface;

    /**
     * Delete UpStream Pay Subscription
     *
     * @param SubscriptionInterface $subscription
     * @return bool true on success
     * @throws LocalizedException
     */
    public function delete(SubscriptionInterface $subscription): bool;

    /**
     * @param int $entityId
     *
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $entityId): bool;
}
