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
interface SubscriptionRetryInterface extends ExtensibleDataInterface
{
    public const ID = 'id';
    public const SUBSCRIPTION_ID = 'subscription_id';
    public const NUMBER_OF_RETRIES = 'number_of_retries';
    public const RETRY_STATUS = 'retry_status';
    public const RETRY_TYPE = 'retry_type';
    public const TRANSACTION_ID = 'transaction_id';

    /**
     * Primary key of the table.
     *
     * @return null|int
     */
    public function getId(): ?int;

    /**
     * Get the related subscription id
     *
     * @return null|int
     */
    public function getSubscriptionId(): ?int;

    /**
     * Set the related subscription id
     *
     * @param int $subscriptionId
     *
     * @return $this
     */
    public function setSubscriptionId(int $subscriptionId): self;

    /**
     * Get the number of retries.
     *
     * @return null|int
     */
    public function getNumberOfRetries(): ?int;

    /**
     * Set the number of retries.
     *
     * @return $this
     */
    public function setNumberOfRetries(int $number): self;

    /**
     * Get retry status.
     *
     * @return string
     */
    public function getRetryStatus(): string;

    /**
     * Set retry status.
     *
     * @return $this
     */
    public function setRetryStatus(string $status): self;

    /**
     * Get retry type.
     *
     * @return null|string
     */
    public function getRetryType(): ?string;

    /**
     * Set retry type.
     *
     * @return $this
     */
    public function setRetryType(string $type): self;

    /**
     * Get related transaction id.
     *
     * @return null|int
     */
    public function getTransactionId(): ?int;

    /**
     * Set related transaction id.
     *
     * @return $this
     */
    public function setTransactionId(int $transactionId): self;

    /**
     * Return true if the retry can be performed, false otherwise.
     *
     * @return bool
     */
    public function canBeRetried(): bool;
}
