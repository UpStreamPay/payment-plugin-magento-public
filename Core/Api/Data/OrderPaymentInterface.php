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

use Magento\Framework\Api\ExtensibleDataInterface;

/**
 * Interface OrderPaymentInterface
 *
 * @package UpStreamPay\Core\Api\Data
 */
interface OrderPaymentInterface extends ExtensibleDataInterface
{
    public const ENTITY_ID = 'entity_id';
    public const SESSION_ID = 'session_id';
    public const DEFAULT_TRANSACTION_ID = 'default_transaction_id';
    public const METHOD = 'method';
    public const TYPE = 'type';
    public const QUOTE_ID = 'quote_id';
    public const ORDER_ID = 'order_id';
    public const PAYMENT_ID = 'payment_id';
    public const AMOUNT = 'amount';
    public const AMOUNT_CAPTURED = 'amount_captured';
    public const AMOUNT_REFUNDED = 'amount_refunded';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * Primary key of the table.
     *
     * @return null|int
     */
    public function getEntityId(): ?int;

    /**
     * Get the session ID used to create the payment.
     *
     * @return string
     */
    public function getSessionId(): string;

    /**
     * Set the session ID used to create the payment.
     * We should only set this on create, never change it after.
     *
     * @param string $sessionId
     *
     * @return $this
     */
    public function setSessionId(string $sessionId): self;

    /**
     * Get the transaction ID (Upstream Pay not magento) for this payment.
     * It's the first transaction on the payment method (authorize or capture for payment with immediate capture)
     * This will be referenced as parent_transaction_id in case of several transaction on one payment method.
     *
     * @return null|string
     */
    public function getDefaultTransactionId(): ?string;

    /**
     * Set the transaction ID (Upstream Pay not magento) for this payment.
     * It's the first transaction on the payment method (authorize or capture for payment with immediate capture)
     * This will be referenced as parent_transaction_id in case of several transaction on one payment method.
     *
     * Only set it on creation, never update it.
     *
     * @param null|string $defaultTransactionId
     *
     * @return $this
     */
    public function setDefaultTransactionId(?string $defaultTransactionId = null): self;

    /**
     * Get the method used for the payment (Upstream Pay method) because Upstream Pay allows multiple payment methods.
     *
     * @return string
     */
    public function getMethod(): string;

    /**
     * Set the method used for the payment (Upstream Pay method) because Upstream Pay allows multiple payment methods.
     * Only set it on creation, never update it.
     *
     * @param string $method
     *
     * @return $this
     */
    public function setMethod(string $method): self;

    /**
     * Get the method type used for the payment (Upstream Pay method), it can be primary or secondary.
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Set the method type used for the payment (Upstream Pay method), it can be primary or secondary.
     * Only set it on creation, never update it.
     *
     * @param string $type
     *
     * @return $this
     */
    public function setType(string $type): self;

    /**
     * Get the quote id associated with the payment.
     *
     * @return int
     */
    public function getQuoteId(): int;

    /**
     * Set the quote id associated with the payment.
     * Only set it on creation, never update it.
     *
     * @param int $quoteId
     *
     * @return $this
     */
    public function setQuoteId(int $quoteId): self;

    /**
     * Get the order id associated with the payment.
     *
     * @return int
     */
    public function getOrderId(): int;

    /**
     * Set the order id associated with the payment.
     * Only set it on creation, never update it.
     *
     * @param int $orderId
     *
     * @return $this
     */
    public function setOrderId(int $orderId): self;

    /**
     * Get the magento payment id.
     *
     * @return int
     */
    public function getPaymentId(): int;

    /**
     * Set the magento payment id
     * Only set it on creation, never update it.
     *
     * @param int $paymentId
     *
     * @return $this
     */
    public function setPaymentId(int $paymentId): self;

    /**
     * Get the amount of the UpStream Pay payment for the payment method (global amount).
     *
     * @return float
     */
    public function getAmount(): float;

    /**
     * Set the amount of the UpStream Pay payment for the payment method (global amount).
     * Only set it on creation, never update it. This amount should always stay the same.
     *
     * @param float $amount
     *
     * @return $this
     */
    public function setAmount(float $amount): self;

    /**
     * Get the amount captured for the UpStream Pay method payment.
     *
     * @return float
     */
    public function getAmountCaptured(): float;

    /**
     * Set the amount captured for the UpStream Pay method payment.
     * Update it for each capture done.
     *
     * @param float $amountCaptured
     *
     * @return $this
     */
    public function setAmountCaptured(float $amountCaptured): self;

    /**
     * Get the amount refunded for the UpStream Pay method payment.
     *
     * @return float
     */
    public function getAmountRefunded(): float;

    /**
     * Get the amount refunded for the UpStream Pay method payment.
     * Update it for each refund done.
     *
     * @param float $amountRefunded
     *
     * @return $this
     */
    public function setAmountRefunded(float $amountRefunded): self;

    /**
     * Get the creation date of the UpStream Pay payment.
     *
     * @return string
     */
    public function getCreatedAt(): string;

    /**
     * Set the creation date of the UpStream Pay payment.
     * The DB will automatically set this info.
     *
     * @param string $createdAt
     *
     * @return $this
     */
    public function setCreatedAt(string $createdAt): self;

    /**
     * Get the last modification date of the UpStream Pay payment.
     *
     * @return string
     */
    public function getUpdatedAt(): string;

    /**
     * Set the last modification date of the UpStream Pay payment.
     * The DB will automatically set this info.
     *
     * @param string $updatedAt
     *
     * @return $this
     */
    public function setUpdatedAt(string $updatedAt): self;
}
