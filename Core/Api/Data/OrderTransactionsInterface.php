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

/**
 * Interface OrderTransactionsInterface
 *
 * @package UpStreamPay\Core\Api\Data
 */
interface OrderTransactionsInterface
{
    public const ENTITY_ID = 'entity_id';
    public const SESSION_ID = 'session_id';
    public const TRANSACTION_ID = 'transaction_id';
    public const PARENT_TRANSACTION_ID = 'parent_transaction_id';
    public const PARENT_PAYMENT_ID = 'parent_payment_id';
    public const METHOD = 'method';
    public const TRANSACTION_TYPE = 'transaction_type';
    public const QUOTE_ID = 'quote_id';
    public const ORDER_ID = 'order_id';
    public const INVOICE_ID = 'invoice_id';
    public const CREDITMEMO_ID = 'creditmemo_id';
    public const AMOUNT = 'amount';
    public const STATUS = 'status';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * @return null|int
     */
    public function getEntityId(): ?int;

    /**
     * Get the session ID linked to the transaction.
     *
     * @return string
     */
    public function getSessionId(): string;

    /**
     * Set the session ID linked to the transaction.
     * Only set when creating the row. Should never change after.
     *
     * @param string $sessionId
     *
     * @return $this
     */
    public function setSessionId(string $sessionId): self;

    /**
     * Get the id for the UpStream Pay transaction.
     *
     * @return string
     */
    public function getTransactionId(): string;

    /**
     * Set the id for the UpStream Pay transaction.
     * Only set when creating the row. Should never change after.
     *
     * @param string $transactionId
     *
     * @return $this
     */
    public function setTransactionId(string $transactionId): self;

    /**
     * Get the parent transaction ID, if it exists.
     *
     * @return null|string
     */
    public function getParentTransactionId(): ?string;

    /**
     * Set the parent transaction if, if it exists.
     * Only set when creating the row. Should never change after.
     *
     * @param null|string $parentTransactionId
     *
     * @return $this
     */
    public function setParentTransactionId(?string $parentTransactionId): self;

    /**
     * Get the parent payment ID, if it exists.
     *
     * @return null|int
     */
    public function getParentPaymentId(): ?int;

    /**
     * Set the parent payment if, if it exists.
     * Only set when creating the row. Should never change after.
     *
     * @param null|int $parentPaymentId
     *
     * @return $this
     */
    public function setParentPaymentId(?int $parentPaymentId): self;

    /**
     * Get payment method name. Concat of partner / method.
     *
     * @return string
     */
    public function getMethod(): string;

    /**
     * Set payment method name. Concat of partner / method.
     * Only set when creating the row. Should never change after.
     *
     * @param string $method
     *
     * @return $this
     */
    public function setMethod(string $method): self;

    /**
     * Get the transaction type (authorize, capture, refund....).
     *
     * @return string
     */
    public function getTransactionType(): string;

    /**
     * Set the transaction type (authorize, capture, refund....).
     * Only set when creating the row. Should never change after.
     *
     * @param string $transactionType
     *
     * @return $this
     */
    public function setTransactionType(string $transactionType): self;

    /**
     * Quote ID linked to the transaction.
     *
     * @return int
     */
    public function getQuoteId(): int;

    /**
     * Quote ID linked to the transaction.
     * Only set when creating the row. Should never change after.
     *
     * @param int $quoteId
     *
     * @return $this
     */
    public function setQuoteId(int $quoteId): self;

    /**
     * Order ID linked to the transaction.
     *
     * @return int
     */
    public function getOrderId(): int;

    /**
     * Order ID linked to the transaction.
     * Only set when creating the row. Should never change after.
     *
     * @param int $orderId
     *
     * @return $this
     */
    public function setOrderId(int $orderId): self;

    /**
     * Invoice ID linked to the transaction, if any.
     *
     * @return null|int
     */
    public function getInvoiceId(): ?int;

    /**
     * Invoice ID linked to the transaction, if any.
     * Only set when creating the row. Should never change after.
     *
     * @param ?int $invoiceId
     *
     * @return $this
     */
    public function setInvoiceId(?int $invoiceId): self;

    /**
     * Creditmemo ID linked to the transaction, if any.
     *
     * @return null|int
     */
    public function getCreditmemoId(): ?int;

    /**
     * Creditmemo ID linked to the transaction, if any.
     * Only set when creating the row. Should never change after.
     *
     * @param ?int $creditmemoId
     *
     * @return $this
     */
    public function setCreditmemoId(?int $creditmemoId): self;

    /**
     * Get the status of the transaction (based on what API returns).
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Set the transaction status.
     *
     * @param string $status
     *
     * @return $this
     */
    public function setStatus(string $status): self;

    /**
     * Get the amount of the transaction.
     *
     * @return float
     */
    public function getAmount(): float;

    /**
     * Set the amount of the transaction.
     * Only set when creating the row. Should never change after.
     *
     * @param float $amount
     *
     * @return $this
     */
    public function setAmount(float $amount): self;

    /**
     * Get the creation date of the UpStream Pay transaction.
     *
     * @return string
     */
    public function getCreatedAt(): string;

    /**
     * Set the creation date of the UpStream Pay transaction.
     * The DB will automatically set this info.
     *
     * @param string $createdAt
     *
     * @return $this
     */
    public function setCreatedAt(string $createdAt): self;

    /**
     * Get the last modification date of the UpStream Pay transaction.
     *
     * @return string
     */
    public function getUpdatedAt(): string;

    /**
     * Set the last modification date of the UpStream Pay transaction.
     * The DB will automatically set this info.
     *
     * @param string $updatedAt
     *
     * @return $this
     */
    public function setUpdatedAt(string $updatedAt): self;
}
