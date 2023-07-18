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
namespace UpStreamPay\Client\Api\Data;

/**
 * Interface TokenInterface
 *
 * @package UpStreamPay\Client\Api\Data
 */
interface TokenInterface
{
    const VALUE = 'value';
    const LIFETIME = 'lifetime';
    const CREATED_AT = 'created_at';
    const EXPIRATION_DATE = 'expiration_date';

    /**
     * @return null|string
     */
    public function getValue(): ?string;

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setValue(string $value): self;

    /**
     * @return null|int
     */
    public function getLifetime(): ?int;

    /**
     * @param int $lifetime
     *
     * @return $this
     */
    public function setLifetime(int $lifetime): self;

    /**
     * @return null|string
     */
    public function getCreatedAt(): ?string;

    /**
     * @param string $createdAt
     *
     * @return $this
     */
    public function setCreatedAt(string $createdAt): self;

    /**
     * @return null|string
     */
    public function getExpirationDate(): ?string;

    /**
     * @param string $expirationDate
     *
     * @return $this
     */
    public function setExpirationDate(string $expirationDate): self;

    /**
     * @return bool
     */
    public function hasExpired(): bool;
}
