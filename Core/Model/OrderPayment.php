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

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use UpStreamPay\Core\Api\Data\OrderPaymentInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
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
     * @codeCoverageIgnore
     *
     * @param OrderPaymentFactory $orderPaymentFactory
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param EventManager $eventManager
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        private readonly OrderPaymentFactory $orderPaymentFactory,
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository,
        private readonly EventManager $eventManager,
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init(PaymentResource::class);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getEntityId(): ?int
    {
        return (int)$this->getData(OrderPaymentInterface::ENTITY_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getSessionId(): string
    {
        return $this->getData(OrderPaymentInterface::SESSION_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setSessionId(string $sessionId): OrderPaymentInterface
    {
       return $this->setData(OrderPaymentInterface::SESSION_ID, $sessionId);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return $this->getData(OrderPaymentInterface::METHOD);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setMethod(string $method): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::METHOD, $method);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getQuoteId(): int
    {
        return $this->getData(OrderPaymentInterface::QUOTE_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setQuoteId(int $quoteId): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::QUOTE_ID, $quoteId);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getOrderId(): int
    {
        return $this->getData(OrderPaymentInterface::ORDER_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setOrderId(int $orderId): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::ORDER_ID, $orderId);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getPaymentId(): int
    {
        return $this->getData(OrderPaymentInterface::PAYMENT_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setPaymentId(int $paymentId): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::PAYMENT_ID, $paymentId);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getAmount(): float
    {
        return (float)$this->getData(OrderPaymentInterface::AMOUNT);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setAmount(float $amount): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::AMOUNT, $amount);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getAmountCaptured(): float
    {
        return (float)$this->getData(OrderPaymentInterface::AMOUNT_CAPTURED);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setAmountCaptured(float $amountCaptured): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::AMOUNT_CAPTURED, $amountCaptured);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getAmountRefunded(): float
    {
        return (float)$this->getData(OrderPaymentInterface::AMOUNT_REFUNDED);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setAmountRefunded(float $amountRefunded): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::AMOUNT_REFUNDED, $amountRefunded);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getCreatedAt(): string
    {
        return $this->getData(OrderPaymentInterface::CREATED_AT);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setCreatedAt(string $createdAt): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::CREATED_AT, $createdAt);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getUpdatedAt(): string
    {
        return $this->getData(OrderPaymentInterface::UPDATED_AT);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setUpdatedAt(string $updatedAt): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::UPDATED_AT, $updatedAt);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getType(): string
    {
        return $this->getData(OrderPaymentInterface::TYPE);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setType(string $type): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::TYPE, $type);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getDefaultTransactionId(): ?string
    {
        return $this->getData(OrderPaymentInterface::DEFAULT_TRANSACTION_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setDefaultTransactionId(?string $defaultTransactionId = null): OrderPaymentInterface
    {
        return $this->setData(OrderPaymentInterface::DEFAULT_TRANSACTION_ID, $defaultTransactionId);
    }

    /**
     * Create an order payment based on an API response.
     *
     * @param array $paymentResponse
     * @param int $orderId
     * @param int $quoteId
     * @param int $paymentId
     * @param string $paymentMethodType
     *
     * @return OrderPaymentInterface
     * @throws LocalizedException
     */
    public function createPaymentFromResponse(
        array $paymentResponse,
        int $orderId,
        int $quoteId,
        int $paymentId,
        string $paymentMethodType
    ): OrderPaymentInterface
    {
        $this->eventManager->dispatch('payment_usp_write_log', [
            'paymentResponse' => $paymentResponse,
            'orderId' => $orderId,
            'quoteId' => $quoteId,
            'paymentId' => $paymentId,
            'paymentMethodType' => $paymentMethodType,
        ]);
        /** @var OrderPaymentInterface $orderPayment */
        $orderPayment = $this->orderPaymentFactory->create();

        $orderPayment
            ->setSessionId($paymentResponse['session_id'])
            ->setDefaultTransactionId($paymentResponse['id'])
            ->setMethod($paymentResponse['partner'] . ' / ' . $paymentResponse['method'])
            ->setType($paymentMethodType)
            ->setQuoteId($quoteId)
            ->setOrderId($orderId)
            ->setPaymentId($paymentId)
            ->setAmount((float)$paymentResponse['plugin_result']['amount'])
            ->setAmountCaptured(0.00)
            ->setAmountRefunded(0.00)
        ;

        return $this->orderPaymentRepository->save($orderPayment);
    }

    /**
     * Create an order payment based on an API response.
     *
     * @param OrderTransactionsInterface $transaction
     * @param int $paymentId
     * @param string $paymentMethodType
     *
     * @return OrderPaymentInterface
     * @throws LocalizedException
     */
    public function createPaymentFromTransaction(
        OrderTransactionsInterface $transaction,
        int $paymentId,
        string $paymentMethodType
    ): OrderPaymentInterface
    {
        $this->eventManager->dispatch('payment_usp_write_log', [
            'orderId' => $transaction->getOrderId(),
            'quoteId' => $transaction->getQuoteId(),
            'paymentId' => $paymentId,
            'paymentMethodType' => $paymentMethodType,
        ]);
        /** @var OrderPaymentInterface $orderPayment */
        $orderPayment = $this->orderPaymentFactory->create();

        $orderPayment
            ->setSessionId($transaction->getSessionId())
            ->setDefaultTransactionId($transaction->getTransactionId())
            ->setMethod($transaction->getMethod())
            ->setType($paymentMethodType)
            ->setQuoteId($transaction->getQuoteId())
            ->setOrderId($transaction->getOrderId())
            ->setPaymentId($paymentId)
            ->setAmount($transaction->getAmount())
            ->setAmountCaptured(0.00)
            ->setAmountRefunded(0.00)
        ;

        return $this->orderPaymentRepository->save($orderPayment);
    }
}
