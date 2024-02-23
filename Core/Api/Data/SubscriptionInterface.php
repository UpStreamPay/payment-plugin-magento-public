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
 * Interface SubscriptionInterface
 *
 * @package UpStreamPay\Core\Api\Data
 */
interface SubscriptionInterface extends ExtensibleDataInterface
{
    public const ENTITY_ID = 'entity_id';
    public const SUBSCRIPTION_IDENTIFIER = 'subscription_identifier';
    public const SUBSCRIPTION_STATUS = 'subscription_status';
    public const PAYMENT_STATUS = 'payment_status';
    public const PRODUCT_PRICE = 'product_price';
    public const PRODUCT_NAME = 'product_name';
    public const PRODUCT_SKU = 'product_sku';
    public const START_DATE = 'start_date';
    public const END_DATE = 'end_date';
    public const NEXT_PAYMENT_DATE = 'next_payment_date';
    public const ORDER_ID = 'order_id';
    public const ORIGINAL_TRANSACTION_ID = 'original_transaction_id';

    /**
     * Primary key of the table.
     *
     * @return null|int
     */
    public function getEntityId(): ?int;

    /**
     * Get the subscription id.
     *
     * @return string
     */
    public function getSubscriptionIdentifier(): string;

    /**
     * Set the subscription identifier
     *
     * @param string $subscriptionIdentifier
     * @return $this
     */
    public function setSubscriptionIdentifier(string $subscriptionIdentifier): self;

    /**
     * Get the status for the UpStream Pay subscription.
     *
     * @return string
     */
    public function getSubscriptionStatus(): string;

    /**
     * Set the status for the UpStream Pay subscription.
     *
     * @param string $subscriptionStatus
     * @return $this
     */
    public function setSubscriptionStatus(string $subscriptionStatus): self;

    /**
     * Get the subscription payment status
     *
     * @return string
     */
    public function getPaymentStatus(): string;

    /**
     * Set the subscription payment status
     *
     * @param string $paymentStatus
     * @return $this
     */
    public function setPaymentStatus(string $paymentStatus): self;

    /**
     * Get the product subscription price.
     *
     * @return float
     */
    public function getProductPrice(): float;

    /**
     * Set the product subscription price
     *
     * @param float $productPrice
     * @return $this
     */
    public function setProductPrice(float $productPrice): self;

    /**
     * Get the product subscription name
     *
     * @return string
     */
    public function getProductName(): string;

    /**
     * Set product subscription name
     *
     * @param string $productName
     * @return $this
     */
    public function setProductName(string $productName): self;

    /**
     * Get the product subscription sku
     *
     * @return string
     */
    public function getProductSku(): string;

    /**
     * Set the product subscription sku
     *
     * @param string $productSku
     * @return $this
     */
    public function setProductSku(string $productSku): self;

    /**
     * Get subscription start date.
     *
     * @return string
     */
    public function getStartDate(): string;

    /**
     * Set the subscription start date
     *
     * @param string $startDate
     * @return $this
     */
    public function setStartDate(string $startDate): self;

    /**
     * Get subscription end date
     *
     * @return string
     */
    public function getEndDate(): string;

    /**
     * Set subscription end date
     *
     * @param string $endDate
     * @return $this
     */
    public function setEndDate(string $endDate): self;

    /**
     * Get the next subscription payment date
     *
     * @return string|null
     */
    public function getNextPaymentDate(): ?string;

    /**
     * Set the next subscription payment date
     *
     * @param string|null $nextPaymentDate
     * @return $this
     */
    public function setNextPaymentDate(?string $nextPaymentDate): self;

    /**
     * Get order id
     *
     * @return ?int
     */
    public function getOrderId(): ?int;

    /**
     * Set the order id
     *
     * @param int $orderId
     * @return $this
     */
    public function setOrderId(int $orderId): self;

    /**
     * Get the original transaction id
     *
     * @return string
     */
    public function getOriginalTransactionId(): string;

    /**
     * Set Original transaction id
     *
     * @param string $originalTransactionId
     * @return $this
     */
    public function setOriginalTransactionId(string $originalTransactionId): self;

}
