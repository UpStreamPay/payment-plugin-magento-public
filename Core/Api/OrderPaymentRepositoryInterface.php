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
use UpStreamPay\Core\Api\Data\OrderPaymentInterface;
use UpStreamPay\Core\Api\Data\OrderPaymentSearchResultsInterface;

/**
 * Interface OrderPaymentRepositoryInterface
 *
 * @package UpStreamPay\Core\Api
 */
interface OrderPaymentRepositoryInterface
{
    /**
     * Save UpStream Pay order payment.
     *
     * @param OrderPaymentInterface $orderPayment
     *
     * @return OrderPaymentInterface
     * @throws LocalizedException
     */
    public function save(OrderPaymentInterface $orderPayment): OrderPaymentInterface;

    /**
     * Get UpStream Pay order payment.
     *
     * @param int $orderPaymentId
     *
     * @return OrderPaymentInterface
     * @throws LocalizedException
     */
    public function getById(int $orderPaymentId): OrderPaymentInterface;

    /**
     * Get UpStream Pay order payment by quote ID.
     *
     * @param int $quoteId
     *
     * @return OrderPaymentInterface[]
     * @throws LocalizedException
     *
     */
    public function getByQuoteId(int $quoteId): array;

    /**
     * Get UpStream Pay order payment by session ID.
     *
     * @param string $sessionId
     *
     * @return OrderPaymentInterface[]
     * @throws LocalizedException
     *
     */
    public function getBySessionId(string $sessionId): array;

    /**
     * Get UpStream Pay order payment by order ID.
     *
     * @param int $orderId
     *
     * @return OrderPaymentInterface[]
     * @throws LocalizedException
     *
     */
    public function getByOrderId(int $orderId): array;

    /**
     * Get UpStream Pay order payment by default transaction ID (that is unique).
     * So we only return one element.
     *
     * @param string $defaultTransactionId
     *
     * @return OrderPaymentInterface
     */
    public function getByDefaultTransactionId(string $defaultTransactionId): OrderPaymentInterface;

    /**
     * Retrieve UpStream Pay order payment matching the specified criteria.
     *
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return OrderPaymentSearchResultsInterface
     * @throws LocalizedException
     */
    public function getList(SearchCriteriaInterface $searchCriteria): OrderPaymentSearchResultsInterface;

    /**
     * Delete UpStream Pay order payment.
     *
     * @param OrderPaymentInterface $orderPayment
     *
     * @return bool true on success
     * @throws LocalizedException
     */
    public function delete(OrderPaymentInterface $orderPayment): bool;

    /**
     * Delete UpStream Pay order payment by ID.
     *
     * @param int $orderPaymentId
     * @return bool true on success
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function deleteById(int $orderPaymentId): bool;
}
