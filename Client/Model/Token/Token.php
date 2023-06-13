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
declare(strict_types=1);

namespace UpStreamPay\Client\Model\Token;

use Magento\Framework\DataObject;
use UpStreamPay\Client\Api\Data\TokenInterface;

/**
 * Class Token
 *
 * @package UpstreamPay\Client\Model\Token
 */
class Token extends DataObject implements TokenInterface
{
    /**
     * @return null|string
     */
    public function getValue(): ?string
    {
        return $this->getData(self::VALUE);
    }

    /**
     * @param string $value
     *
     * @return $this
     */
    public function setValue(string $value): self
    {
        return $this->setData(self::VALUE, $value);
    }

    /**
     * @return null|int
     */
    public function getLifetime(): ?int
    {
        return (int) $this->getData(self::LIFETIME);
    }

    /**
     * @param int $lifetime
     *
     * @return $this
     */
    public function setLifetime(int $lifetime): self
    {
        return $this->setData(self::LIFETIME, $lifetime);
    }

    /**
     * @return null|string
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @param string $createdAt
     *
     * @return $this
     */
    public function setCreatedAt(string $createdAt): self
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @return null|string
     */
    public function getExpirationDate(): ?string
    {
        return $this->getData(self::EXPIRATION_DATE);
    }

    /**
     * @param string $expirationDate
     *
     * @return $this
     */
    public function setExpirationDate(string $expirationDate): self
    {
        return $this->setData(self::EXPIRATION_DATE, $expirationDate);
    }

    /**
     * @return bool
     */
    public function hasExpired(): bool
    {
        if ($this->getExpirationDate() === null || $this->getCreatedAt() === null) {
            return true;
        }

        return $this->getExpirationDate() < $this->getCreatedAt();
    }
}
