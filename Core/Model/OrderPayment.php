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

namespace UpStreamPay\Core\Model;

use Magento\Framework\Model\AbstractExtensibleModel;
use UpStreamPay\Core\Api\Data\OrderPaymentInterface;
use UpStreamPay\Core\Model\ResourceModel\OrderPayment as PaymentResource;

/**
 * Class OrderPayment
 *
 * @package UpStreamPay\Core\Model
 */
class OrderPayment extends AbstractExtensibleModel implements OrderPaymentInterface
{
    protected $_eventPrefix = 'upstream_pay_order_payment';

    protected $_eventObject = 'order_payment';

    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(PaymentResource::class);
    }

    /**
     * @inheritDoc
     */
    public function getEntityId(): ?int
    {
        return $this->getData(OrderPaymentInterface::ENTITY_ID);
    }

    /**
     * @inheritDoc
     */
    public function getSessionId(): string
    {
        return $this->getData(OrderPaymentInterface::SESSION_ID);
    }

    /**
     * @inheritDoc
     */
    public function setSessionId(string $sessionId): OrderPaymentInterface
    {
       return $this->setData(OrderPaymentInterface::SESSION_ID, $sessionId);
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return $this->getData(OrderPaymentInterface::METHOD);
    }

    /**
     * @inheritDoc
     */
    public function setMethod(string $method): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::METHOD, $method);
    }

    /**
     * @inheritDoc
     */
    public function getQuoteId(): int
    {
        return $this->getData(OrderPaymentInterface::QUOTE_ID);
    }

    /**
     * @inheritDoc
     */
    public function setQuoteId(int $quoteId): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::QUOTE_ID, $quoteId);
    }

    /**
     * @inheritDoc
     */
    public function getOrderId(): int
    {
        return $this->getData(OrderPaymentInterface::ORDER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setOrderId(int $orderId): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::ORDER_ID, $orderId);
    }

    /**
     * @inheritDoc
     */
    public function getPaymentId(): int
    {
        return $this->getData(OrderPaymentInterface::PAYMENT_ID);
    }

    /**
     * @inheritDoc
     */
    public function setPaymentId(int $paymentId): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::PAYMENT_ID, $paymentId);
    }

    /**
     * @inheritDoc
     */
    public function getAmount(): float
    {
        return $this->getData(OrderPaymentInterface::AMOUNT);
    }

    /**
     * @inheritDoc
     */
    public function setAmount(float $amount): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::AMOUNT, $amount);
    }

    /**
     * @inheritDoc
     */
    public function getAmountCaptured(): float
    {
        return $this->getData(OrderPaymentInterface::AMOUNT_CAPTURED);
    }

    /**
     * @inheritDoc
     */
    public function setAmountCaptured(float $amountCaptured): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::AMOUNT_CAPTURED, $amountCaptured);
    }

    /**
     * @inheritDoc
     */
    public function getAmountRefunded(): float
    {
        return $this->getData(OrderPaymentInterface::AMOUNT_REFUNDED);
    }

    /**
     * @inheritDoc
     */
    public function setAmountRefunded(float $amountRefunded): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::AMOUNT_REFUNDED, $amountRefunded);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): string
    {
        return $this->getData(OrderPaymentInterface::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(string $createdAt): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): string
    {
        return $this->getData(OrderPaymentInterface::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setUpdatedAt(string $updatedAt): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::UPDATED_AT, $updatedAt);
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return $this->getData(OrderPaymentInterface::TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setType(string $type): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::TYPE, $type);
    }

    /**
     * @inheritDoc
     */
    public function getDefaultTransactionId(): ?string
    {
        return $this->getData(OrderPaymentInterface::DEFAULT_TRANSACTION_ID);
    }

    /**
     * @inheritDoc
     */
    public function setDefaultTransactionId(?string $defaultTransactionId = null): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::DEFAULT_TRANSACTION_ID, $defaultTransactionId);
    }
}
