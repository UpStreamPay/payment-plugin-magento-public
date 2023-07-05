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
namespace UpStreamPay\Core\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsSearchResultsInterface;

/**
 * Interface OrderTransactionsRepositoryInterface
 *
 * @package UpStreamPay\Core\Api
 */
interface OrderTransactionsRepositoryInterface
{
    /**
     * @param OrderTransactionsInterface $orderTransaction
     *
     * @return OrderTransactionsInterface
     * @throws LocalizedException
     */
    public function save(OrderTransactionsInterface $orderTransaction): OrderTransactionsInterface;

    /**
     * @param int $entityId
     *
     * @return OrderTransactionsInterface
     * @throws LocalizedException
     */
    public function getById(int $entityId): OrderTransactionsInterface;

    /**
     * @param string $transactionId
     *
     * @return OrderTransactionsInterface
     * @throws LocalizedException
     */
    public function getByTransactionId(string $transactionId): OrderTransactionsInterface;

    /**
     * @param string $parentTransactionId
     *
     * @return OrderTransactionsInterface[]
     * @throws LocalizedException
     */
    public function getByParentTransactionId(string $parentTransactionId): array;

    /**
     * @param int $orderId
     *
     * @return OrderTransactionsInterface[]
     * @throws LocalizedException
     */
    public function getByOrderId(int $orderId): array;

    /**
     * @param int $quoteId
     *
     * @return OrderTransactionsInterface[]
     * @throws LocalizedException
     */
    public function getByQuoteId(int $quoteId): array;

    /**
     * @param int $invoiceId
     *
     * @return OrderTransactionsInterface[]
     * @throws LocalizedException
     */
    public function getByInvoiceId(int $invoiceId): array;

    /**
     * @param int $creditmemoId
     *
     * @return OrderTransactionsInterface[]
     * @throws LocalizedException
     */
    public function getByCreditmemoId(int $creditmemoId): array;

    /**
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return OrderTransactionsSearchResultsInterface
     * @throws LocalizedException
     */
    public function getList(SearchCriteriaInterface $searchCriteria): OrderTransactionsSearchResultsInterface;

    /**
     * @param OrderTransactionsInterface $orderTransactions
     *
     * @return bool
     * @throws LocalizedException
     */
    public function delete(OrderTransactionsInterface $orderTransactions): bool;

    /**
     * @param int $entityId
     *
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $entityId): bool;
}
