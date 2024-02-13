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
use UpStreamPay\Core\Api\Data\SubscriptionRetryInterface;
use UpStreamPay\Core\Api\Data\SubscriptionRetrySearchResultsInterface;

/**
 * Interface SubscriptionRetryRepositoryInterface
 *
 * @package UpStreamPay\Core\Api
 */
interface SubscriptionRetryRepositoryInterface
{
    /**
     * Save subscription retry
     *
     * @param SubscriptionRetryInterface $subscriptionRetry
     *
     * @return SubscriptionRetryInterface
     * @throws LocalizedException
     */
    public function save(SubscriptionRetryInterface $subscriptionRetry): SubscriptionRetryInterface;

    /**
     * Get subscription retry by id
     *
     * @param int $id
     *
     * @return SubscriptionRetryInterface
     * @throws LocalizedException
     */
    public function getById(int $id): SubscriptionRetryInterface;

    /**
     * Get subscription retry by id
     *
     * @param int $subscriptionId
     *
     * @return SubscriptionRetryInterface
     * @throws LocalizedException
     */
    public function getBySubscriptionId(int $subscriptionId): SubscriptionRetryInterface;

    /**
     * Get subscription retry by id
     *
     * @param int $transactionId
     *
     * @return SubscriptionRetryInterface
     * @throws LocalizedException
     */
    public function getByTransactionId(int $transactionId): SubscriptionRetryInterface;

    /**
     * Retrieve subscription retry matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return SubscriptionRetrySearchResultsInterface
     * @throws LocalizedException
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SubscriptionRetrySearchResultsInterface;

    /**
     * Delete subscription retry
     *
     * @param SubscriptionRetryInterface $subscriptionRetry
     *
     * @return bool true on success
     * @throws LocalizedException
     */
    public function delete(SubscriptionRetryInterface $subscriptionRetry): bool;

    /**
     * Delete subscription retry by ID.
     *
     * @param int $id
     * @return bool true on success
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function deleteById(int $id): bool;
}
