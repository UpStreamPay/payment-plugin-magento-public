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
 * Interface PaymentMethodInterface
 *
 * @package UpStreamPay\Core\Api\Data
 */
interface PaymentMethodInterface extends ExtensibleDataInterface
{
    public const ENTITY_ID = 'entity_id';
    public const METHOD = 'method';
    public const TYPE = 'type';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * Primary key of the table.
     *
     * @return null|int
     */
    public function getEntityId(): ?int;

    /**
     * Get the method (Upstream Pay method).
     *
     * @return string
     */
    public function getMethod(): string;

    /**
     * Set the method (Upstream Pay method).
     * Only set it on creation, never update it.
     *
     * @param string $method
     *
     * @return $this
     */
    public function setMethod(string $method): self;

    /**
     * Get the method type (Upstream Pay method), it can be primary or secondary.
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Set the method type (Upstream Pay method), it can be primary or secondary.
     *
     * @param string $type
     *
     * @return $this
     */
    public function setType(string $type): self;

    /**
     * Get the creation date of the UpStream Pay payment method.
     *
     * @return string
     */
    public function getCreatedAt(): string;

    /**
     * Set the creation date of the UpStream Pay payment method.
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
     * Set the last modification date of the UpStream Pay payment method.
     * The DB will automatically set this info.
     *
     * @param string $updatedAt
     *
     * @return $this
     */
    public function setUpdatedAt(string $updatedAt): self;
}
