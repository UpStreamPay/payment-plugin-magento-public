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
declare(strict_types=1);

namespace UpStreamPay\Core\Model;

use Magento\Framework\Model\AbstractExtensibleModel;
use UpStreamPay\Core\Api\Data\SubscriptionInterface;
use UpStreamPay\Core\Model\ResourceModel\Subscription as SubscriptionResource;

/**
 * Class Subscription
 *
 * @package UpStreamPay\Core\Model
 */
class Subscription extends AbstractExtensibleModel implements SubscriptionInterface
{
    protected $_eventPrefix = 'upstream_pay_subscription';

    protected $_eventObject = 'subscription';

    public const DISABLED = 'disabled';
    public const ENABLED = 'enabled';
    public const EXPIRED = 'expired';
    public const CANCELED = 'canceled';
    public const TO_PAY = 'to_pay';
    public const PAID = 'paid';
    public const RETRY_PAYMENT = 'retry_payment';
    public const ERROR = 'error';


    /**
     * @inheritDoc
     *
     * @codeCoverageIgnore
     */
    protected function _construct()
    {
        $this->_init(SubscriptionResource::class);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getEntityId(): ?int
    {
        return (int)$this->getData(SubscriptionInterface::ENTITY_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getSubscriptionIdentifier(): string
    {
        return $this->getData(SubscriptionInterface::SUBSCRIPTION_IDENTIFIER);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setSubscriptionIdentifier(string $subscriptionIdentifier): SubscriptionInterface
    {
        return $this->setData(SubscriptionInterface::SUBSCRIPTION_IDENTIFIER, $subscriptionIdentifier);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getSubscriptionStatus(): string
    {
        return $this->getData(SubscriptionInterface::SUBSCRIPTION_STATUS);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setSubscriptionStatus(string $subscriptionStatus): SubscriptionInterface
    {
        return $this->setData(SubscriptionInterface::SUBSCRIPTION_STATUS, $subscriptionStatus);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getPaymentStatus(): string
    {
        return $this->getData(SubscriptionInterface::PAYMENT_STATUS);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setPaymentStatus(string $paymentStatus): SubscriptionInterface
    {
        return $this->setData(SubscriptionInterface::PAYMENT_STATUS, $paymentStatus);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getProductPrice(): float
    {
        return (float)$this->getData(SubscriptionInterface::PRODUCT_PRICE);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setProductPrice(float $productPrice): SubscriptionInterface
    {
        return $this->setData(SubscriptionInterface::PRODUCT_PRICE, $productPrice);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getProductName(): string
    {
        return $this->getData(SubscriptionInterface::PRODUCT_NAME);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setProductName(string $productName): SubscriptionInterface
    {
        return $this->setData(SubscriptionInterface::PRODUCT_NAME, $productName);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getProductSku(): string
    {
        return $this->getData(SubscriptionInterface::PRODUCT_SKU);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setProductSku(string $productSku): SubscriptionInterface
    {
        return $this->setData(SubscriptionInterface::PRODUCT_SKU, $productSku);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getStartDate(): string
    {
        return $this->getData(SubscriptionInterface::START_DATE);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setStartDate(string $startDate): SubscriptionInterface
    {
        return $this->setData(SubscriptionInterface::START_DATE, $startDate);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getEndDate(): string
    {
        return $this->getData(SubscriptionInterface::END_DATE);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setEndDate(string $endDate): SubscriptionInterface
    {
        return $this->setData(SubscriptionInterface::END_DATE, $endDate);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getNextPaymentDate(): ?string
    {
        return $this->getData(SubscriptionInterface::NEXT_PAYMENT_DATE);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setNextPaymentDate(?string $nextPaymentDate): SubscriptionInterface
    {
        return $this->setData(SubscriptionInterface::NEXT_PAYMENT_DATE, $nextPaymentDate);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getOrderId(): ?int
    {
        return (int)$this->getData(SubscriptionInterface::ORDER_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setOrderId(int $orderId): SubscriptionInterface
    {
        return $this->setData(SubscriptionInterface::ORDER_ID, $orderId);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getOriginalTransactionId(): string
    {
        return $this->getData(SubscriptionInterface::ORIGINAL_TRANSACTION_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setOriginalTransactionId(string $originalTransactionId): SubscriptionInterface
    {
        return $this->setData(SubscriptionInterface::ORIGINAL_TRANSACTION_ID, $originalTransactionId);
    }

    /**
     * Return true if a subscription can be canceled :
     * payment_status = 'to_pay'
     * subscription_status = 'disabled'
     *
     * @return bool
     */
    public function canCancel(): bool
    {
        return $this->getPaymentStatus() === self::TO_PAY && $this->getSubscriptionStatus() === self::DISABLED;
    }

}
