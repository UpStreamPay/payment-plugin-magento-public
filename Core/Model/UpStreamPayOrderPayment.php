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

use Magento\Framework\Model\AbstractModel;
use UpStreamPay\Core\Api\Data\UpStreamPayOrderPaymentInterface;
use UpStreamPay\Core\Model\ResourceModel\UpStreamPayOrderPayment as PaymentResource;

/**
 * Class UpStreamPayOrderPayment
 *
 * @package UpStreamPay\Core\Model
 */
class UpStreamPayOrderPayment extends AbstractModel implements UpStreamPayOrderPaymentInterface
{
    protected $_eventPrefix = 'upstream_pay_order_payment';

    protected $_eventObject = 'upstream_pay_order_payment';

    /**
     * @return void
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
        return $this->getData(UpStreamPayOrderPaymentInterface::ENTITY_ID);
    }

    /**
     * @inheritDoc
     */
    public function getSessionId(): string
    {
        return $this->getData(UpStreamPayOrderPaymentInterface::SESSION_ID);
    }

    /**
     * @inheritDoc
     */
    public function setSessionId(string $sessionId): UpStreamPayOrderPaymentInterface
    {
       return $this->setData(UpStreamPayOrderPaymentInterface::SESSION_ID, $sessionId);
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return $this->getData(UpStreamPayOrderPaymentInterface::METHOD);
    }

    /**
     * @inheritDoc
     */
    public function setMethod(string $method): UpStreamPayOrderPaymentInterface
    {
        return $this->setData(UpStreamPayOrderPaymentInterface::METHOD, $method);
    }

    /**
     * @inheritDoc
     */
    public function getQuoteId(): int
    {
        return $this->getData(UpStreamPayOrderPaymentInterface::QUOTE_ID);
    }

    /**
     * @inheritDoc
     */
    public function setQuoteId(int $quoteId): UpStreamPayOrderPaymentInterface
    {
        return $this->setData(UpStreamPayOrderPaymentInterface::QUOTE_ID, $quoteId);
    }

    /**
     * @inheritDoc
     */
    public function getOrderId(): int
    {
        return $this->getData(UpStreamPayOrderPaymentInterface::ORDER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setOrderId(int $orderId): UpStreamPayOrderPaymentInterface
    {
        return $this->setData(UpStreamPayOrderPaymentInterface::ORDER_ID, $orderId);
    }

    /**
     * @inheritDoc
     */
    public function getPaymentId(): int
    {
        return $this->getData(UpStreamPayOrderPaymentInterface::PAYMENT_ID);
    }

    /**
     * @inheritDoc
     */
    public function setPaymentId(int $paymentId): UpStreamPayOrderPaymentInterface
    {
        return $this->setData(UpStreamPayOrderPaymentInterface::PAYMENT_ID, $paymentId);
    }

    /**
     * @inheritDoc
     */
    public function getAmount(): float
    {
        return $this->getData(UpStreamPayOrderPaymentInterface::AMOUNT);
    }

    /**
     * @inheritDoc
     */
    public function setAmount(float $amount): UpStreamPayOrderPaymentInterface
    {
        return $this->setData(UpStreamPayOrderPaymentInterface::AMOUNT, $amount);
    }

    /**
     * @inheritDoc
     */
    public function getAmountCaptured(): float
    {
        return $this->getData(UpStreamPayOrderPaymentInterface::AMOUNT_CAPTURED);
    }

    /**
     * @inheritDoc
     */
    public function setAmountCaptured(float $amountCaptured): UpStreamPayOrderPaymentInterface
    {
        return $this->setData(UpStreamPayOrderPaymentInterface::AMOUNT_CAPTURED, $amountCaptured);
    }

    /**
     * @inheritDoc
     */
    public function getAmountRefunded(): float
    {
        return $this->getData(UpStreamPayOrderPaymentInterface::AMOUNT_REFUNDED);
    }

    /**
     * @inheritDoc
     */
    public function setAmountRefunded(float $amountRefunded): UpStreamPayOrderPaymentInterface
    {
        return $this->setData(UpStreamPayOrderPaymentInterface::AMOUNT_REFUNDED, $amountRefunded);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): string
    {
        return $this->getData(UpStreamPayOrderPaymentInterface::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(string $createdAt): UpStreamPayOrderPaymentInterface
    {
        return $this->setData(UpStreamPayOrderPaymentInterface::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): string
    {
        return $this->getData(UpStreamPayOrderPaymentInterface::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setUpdatedAt(string $updatedAt): UpStreamPayOrderPaymentInterface
    {
        return $this->setData(UpStreamPayOrderPaymentInterface::UPDATED_AT, $updatedAt);
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return $this->getData(UpStreamPayOrderPaymentInterface::TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setType(string $type): UpStreamPayOrderPaymentInterface
    {
        return $this->setData(UpStreamPayOrderPaymentInterface::TYPE, $type);
    }

    /**
     * @inheritDoc
     */
    public function getDefaultTransactionId(): ?string
    {
        return $this->getData(UpStreamPayOrderPaymentInterface::DEFAULT_TRANSACTION_ID);
    }

    /**
     * @inheritDoc
     */
    public function setDefaultTransactionId(?string $defaultTransactionId = null): UpStreamPayOrderPaymentInterface
    {
        return $this->setData(UpStreamPayOrderPaymentInterface::DEFAULT_TRANSACTION_ID, $defaultTransactionId);
    }
}
