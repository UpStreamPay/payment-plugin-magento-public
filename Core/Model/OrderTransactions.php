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

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Model\ResourceModel\OrderTransactions as ResourceModel;
use Magento\Framework\Event\ManagerInterface as EventManager;

/**
 * Class OrderTransactions
 *
 * @package UpStreamPay\Core\Model
 */
class OrderTransactions extends AbstractModel implements OrderTransactionsInterface
{
    public const AUTHORIZE_ACTION = 'AUTHORIZE';
    public const CAPTURE_ACTION = 'CAPTURE';
    public const REFUND_ACTION = 'REFUND';
    public const VOID_ACTION = 'VOID';
    public const ORDER_ACTION = 'ORDER';

    public const WAITING_STATUS = 'WAITING';
    public const SUCCESS_STATUS = 'SUCCESS';
    public const ERROR_STATUS = 'ERROR';

    protected $_eventPrefix = 'upstream_pay_order_transactions';

    protected $_eventObject = 'order_transactions';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(ResourceModel::class);
    }

    public function __construct(
        private readonly OrderTransactionsFactory $orderTransactionsFactory,
        private readonly OrderTransactionsRepositoryInterface $transactionsRepository,
        private readonly EventManager $eventManager,
        Context $context,
        Registry $registry,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $resource, $resourceCollection, $data);
    }

    /**
     * @inheritDoc
     */
    public function getEntityId(): ?int
    {
        return (int)$this->getData(self::ENTITY_ID);
    }

    /**
     * @inheritDoc
     */
    public function getTransactionId(): string
    {
        return $this->getData(self::TRANSACTION_ID);
    }

    /**
     * @inheritDoc
     */
    public function setTransactionId(string $transactionId): self
    {
        return $this->setData(self::TRANSACTION_ID, $transactionId);
    }

    /**
     * @inheritDoc
     */
    public function getSessionId(): string
    {
        return $this->getData(self::SESSION_ID);
    }

    /**
     * @inheritDoc
     */
    public function setSessionId(string $sessionId): self
    {
        return $this->setData(self::SESSION_ID, $sessionId);
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return $this->getData(self::METHOD);
    }

    /**
     * @inheritDoc
     */
    public function setMethod(string $method): self
    {
        return $this->setData(self::METHOD, $method);
    }

    /**
     * @inheritDoc
     */
    public function getTransactionType(): string
    {
        return $this->getData(self::TRANSACTION_TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setTransactionType(string $transactionType): self
    {
        return $this->setData(self::TRANSACTION_TYPE, $transactionType);
    }

    /**
     * @inheritDoc
     */
    public function getQuoteId(): int
    {
        return (int)$this->getData(self::QUOTE_ID);
    }

    /**
     * @inheritDoc
     */
    public function setQuoteId(int $quoteId): self
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    /**
     * @inheritDoc
     */
    public function getOrderId(): int
    {
        return (int)$this->getData(self::ORDER_ID);
    }

    /**
     * @inheritDoc
     */
    public function setOrderId(int $orderId): self
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * @inheritDoc
     */
    public function getInvoiceId(): ?int
    {
        return $this->getData(self::INVOICE_ID) === null ? null : (int)$this->getData(self::INVOICE_ID);
    }

    /**
     * @inheritDoc
     */
    public function setInvoiceId(?int $invoiceId): self
    {
        return $this->setData(self::INVOICE_ID, $invoiceId);
    }

    /**
     * @inheritDoc
     */
    public function getCreditmemoId(): ?int
    {
        return (int)$this->getData(self::CREDITMEMO_ID);
    }

    /**
     * @inheritDoc
     */
    public function setCreditmemoId(?int $creditmemoId): self
    {
        return $this->setData(self::CREDITMEMO_ID, $creditmemoId);
    }

    /**
     * @inheritDoc
     */
    public function getStatus(): string
    {
        return $this->getData(self::STATUS);
    }

    /**
     * @inheritDoc
     */
    public function setStatus(string $status): self
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @inheritDoc
     */
    public function getAmount(): float
    {
        return (float)$this->getData(self::AMOUNT);
    }

    /**
     * @inheritDoc
     */
    public function setAmount(float $amount): self
    {
        return $this->setData(self::AMOUNT, $amount);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(string $createdAt): self
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setUpdatedAt(string $updatedAt): self
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    /**
     * @inheritDoc
     */
    public function getParentTransactionId(): ?string
    {
        return $this->getData(self::PARENT_TRANSACTION_ID);
    }

    /**
     * @inheritDoc
     */
    public function setParentTransactionId(?string $parentTransactionId): self
    {
        return $this->setData(self::PARENT_TRANSACTION_ID, $parentTransactionId);
    }

    /**
     * @inheritDoc
     */
    public function getParentPaymentId(): ?int
    {
        return (int)$this->getData(self::PARENT_PAYMENT_ID);
    }

    /**
     * @inheritDoc
     */
    public function setParentPaymentId(?int $parentPaymentId): self
    {
        return $this->setData(self::PARENT_PAYMENT_ID, $parentPaymentId);
    }

    /**
     * Create an order transaction based on an API response.
     *
     * @param array $transactionResponse
     * @param int $orderId
     * @param int $quoteId
     * @param null|int $parentPaymentId
     * @param null|int $invoiceId
     *
     * @return OrderTransactionsInterface
     * @throws LocalizedException
     */
    public function createTransactionFromResponse(
        array $transactionResponse,
        int $orderId,
        int $quoteId,
        ?int $parentPaymentId = null,
        ?int $invoiceId = null,
    ): OrderTransactionsInterface
    {
        $this->eventManager->dispatch('payment_usp_write_log', [
            'transactionResponse' => $transactionResponse,
            'orderId' => $orderId,
            'quoteId' => $quoteId,
            'parentPaymentId' => $parentPaymentId,
            'invoiceId' => $invoiceId
        ]);

        $orderTransaction = $this->orderTransactionsFactory->create();

        $orderTransaction
            ->setTransactionId($transactionResponse['id'])
            ->setSessionId($transactionResponse['session_id'])
            ->setParentTransactionId($transactionResponse['parent_transaction_id'] ?? null)
            ->setParentPaymentId($parentPaymentId)
            ->setMethod($transactionResponse['partner'] . ' / ' . $transactionResponse['method'])
            ->setTransactionType($transactionResponse['status']['action'])
            ->setQuoteId($quoteId)
            ->setOrderId($orderId)
            ->setInvoiceId($invoiceId)
            ->setCreditmemoId(null)
            ->setAmount((float)$transactionResponse['plugin_result']['amount'])
            ->setStatus($transactionResponse['status']['state'])
        ;

        return $this->transactionsRepository->save($orderTransaction);
    }

    /**
     * Get all refunds transactions linked to a given capture transaction ID.
     *
     * @param string $captureTransactionId
     *
     * @return OrderTransactionsInterface[]
     * @throws LocalizedException
     */
    public function getRefundTransactionsFromCapture(string $captureTransactionId): array
    {
        return $this->transactionsRepository->getByParentTransactionId($captureTransactionId);
    }
}
