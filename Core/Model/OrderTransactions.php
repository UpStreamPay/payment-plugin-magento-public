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
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use UpStreamPay\Core\Api\Data\OrderPaymentInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Model\ResourceModel\OrderTransactions as ResourceModel;

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
    public const ORDER_CAPTURE_ACTION = 'ORDER_CAPTURE';
    public const ORDER_CANCEL = 'CANCEL';

    public const CHILD_CAPTURE_TYPE = 'CHILD_CAPTURE';

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

    /**
     * @codeCoverageIgnore
     *
     * @param OrderTransactionsFactory $orderTransactionsFactory
     * @param OrderTransactionsRepositoryInterface $transactionsRepository
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param EventManager $eventManager
     * @param Context $context
     * @param Registry $registry
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        private readonly OrderTransactionsFactory $orderTransactionsFactory,
        private readonly OrderTransactionsRepositoryInterface $transactionsRepository,
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository,
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
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getEntityId(): ?int
    {
        return (int)$this->getData(self::ENTITY_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getTransactionId(): string
    {
        return $this->getData(self::TRANSACTION_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setTransactionId(string $transactionId): self
    {
        return $this->setData(self::TRANSACTION_ID, $transactionId);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getSessionId(): string
    {
        return $this->getData(self::SESSION_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setSessionId(string $sessionId): self
    {
        return $this->setData(self::SESSION_ID, $sessionId);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return $this->getData(self::METHOD);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setMethod(string $method): self
    {
        return $this->setData(self::METHOD, $method);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getTransactionType(): string
    {
        return $this->getData(self::TRANSACTION_TYPE);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setTransactionType(string $transactionType): self
    {
        return $this->setData(self::TRANSACTION_TYPE, $transactionType);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getQuoteId(): int
    {
        return (int)$this->getData(self::QUOTE_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setQuoteId(int $quoteId): self
    {
        return $this->setData(self::QUOTE_ID, $quoteId);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getOrderId(): int
    {
        return (int)$this->getData(self::ORDER_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setOrderId(int $orderId): self
    {
        return $this->setData(self::ORDER_ID, $orderId);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getInvoiceId(): ?int
    {
        return $this->getData(self::INVOICE_ID) === null ? null : (int)$this->getData(self::INVOICE_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setInvoiceId(?int $invoiceId): self
    {
        return $this->setData(self::INVOICE_ID, $invoiceId);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getStatus(): string
    {
        return $this->getData(self::STATUS);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setStatus(string $status): self
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getAmount(): float
    {
        return (float)$this->getData(self::AMOUNT);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setAmount(float $amount): self
    {
        return $this->setData(self::AMOUNT, $amount);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getCreatedAt(): string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setCreatedAt(string $createdAt): self
    {
        return $this->setData(self::CREATED_AT, $createdAt);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getUpdatedAt(): string
    {
        return $this->getData(self::UPDATED_AT);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setUpdatedAt(string $updatedAt): self
    {
        return $this->setData(self::UPDATED_AT, $updatedAt);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getParentTransactionId(): ?string
    {
        return $this->getData(self::PARENT_TRANSACTION_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setParentTransactionId(?string $parentTransactionId): self
    {
        return $this->setData(self::PARENT_TRANSACTION_ID, $parentTransactionId);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getParentPaymentId(): ?int
    {
        return (int)$this->getData(self::PARENT_PAYMENT_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setParentPaymentId(?int $parentPaymentId): self
    {
        return $this->setData(self::PARENT_PAYMENT_ID, $parentPaymentId);
    }

    /**
     * @codeCoverageIgnore
     *
     * @return null|int
     */
    public function getSubscriptionId(): ?int
    {
        return (int)$this->getData(self::SUBSCRIPTION_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @param null|int $subscriptionId
     *
     * @return OrderTransactionsInterface
     */
    public function setSubscriptionId(?int $subscriptionId): OrderTransactionsInterface
    {
        return $this->setData(self::SUBSCRIPTION_ID, $subscriptionId);
    }

    /**
     * @codeCoverageIgnore
     *
     * Create an order transaction based on an API response.
     *
     * @param array $transactionResponse
     * @param int $orderId
     * @param int $quoteId
     * @param null|int $parentPaymentId
     * @param null|int $invoiceId
     * @param null|int $subscriptionId
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
        ?int $subscriptionId = null
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
            ->setAmount((float)$transactionResponse['plugin_result']['amount'])
            ->setStatus($transactionResponse['status']['state'])
            ->setSubscriptionId($subscriptionId)
        ;

        return $this->transactionsRepository->save($orderTransaction);
    }

    /**
     * @codeCoverageIgnore
     *
     * Get all refunds transactions linked to a given capture transaction ID.
     *
     * @param string $captureTransactionId
     *
     * @return OrderTransactionsInterface[]
     * @throws LocalizedException
     */
    public function getRefundTransactionsFromCapture(string $captureTransactionId): array
    {
        return $this->transactionsRepository->getByParentTransactionId($captureTransactionId, self::REFUND_ACTION);
    }

    /**
     * Get all captures transactions linked to a given authorize transaction ID.
     *
     * @codeCoverageIgnore
     *
     * @param string $authorizeTransactionId
     *
     * @return OrderTransactionsInterface[]
     * @throws LocalizedException
     */
    public function getCaptureTransactionsFromAuthorize(string $authorizeTransactionId): array
    {
        return $this->transactionsRepository->getByParentTransactionId($authorizeTransactionId, self::CAPTURE_ACTION);
    }

    /**
     * Get all void transactions linked to a given authorize transaction ID.
     *
     * @codeCoverageIgnore
     *
     * @param string $authorizeTransactionId
     *
     * @return OrderTransactionsInterface[]
     * @throws LocalizedException
     */
    public function getVoidTransactionsFromAuthorize(string $authorizeTransactionId): array
    {
        return $this->transactionsRepository->getByParentTransactionId($authorizeTransactionId, self::VOID_ACTION);
    }

    /**
     * Get all child captures from a parent capture.
     * In case of a partial capture on a capture transaction, we have to create child capture to have the details of
     * what has been captured, linked to what invoice etc...
     *
     * @codeCoverageIgnore
     *
     * @param string $parentCaptureTransactionId
     *
     * @return OrderTransactionsInterface[]
     * @throws LocalizedException
     */
    public function getChildCapturesTransactionsFromCapture(string $parentCaptureTransactionId): array
    {
        return $this->transactionsRepository->getByParentTransactionId(
            $parentCaptureTransactionId, self::CHILD_CAPTURE_TYPE
        );
    }

    /**
     * Get amount captured on every child capture transaction of parent capture transaction.
     *
     * @param string $parentCaptureTransactionId
     *
     * @return float
     * @throws LocalizedException
     */
    public function getAmountCapturedOnChildCapturesTransaction(string $parentCaptureTransactionId): float
    {
        $amountCaptured = 0;
        $childCapturesTransactions = $this->getChildCapturesTransactionsFromCapture($parentCaptureTransactionId);

        foreach ($childCapturesTransactions as $childCaptureTransaction) {
            $amountCaptured += $childCaptureTransaction->getAmount();
        }

        return $amountCaptured;
    }

    /**
     * Get the amount left to capture on transaction based on what has been capture on the UpStream Pay payment.
     *
     * @param int $upsPaymentId
     *
     * @return float
     * @throws LocalizedException
     */
    public function getAmountLeftToCaptureOnTransaction(int $upsPaymentId): float
    {
        $upsPayment = $this->getPaymentLinkedToTransaction($upsPaymentId);

        return $upsPayment->getAmount() - $upsPayment->getAmountCaptured();
    }

    /**
     * Calculate the amount used on an authorize transaction.
     * This means any amount that is not linked to a capture success or waiting.
     * And any amount that is not linked to a void transaction.
     *
     * This calculates the amount we can use to capture on a given authorize transaction.
     *
     * @param string $authorizeTransactionId
     *
     * @return float
     * @throws LocalizedException
     */
    public function getAmountUsedOnAuthorizeTransaction(string $authorizeTransactionId): float
    {
        $transactions = $this->transactionsRepository->getByParentTransactionId($authorizeTransactionId);
        $amountUsed = 0;

        foreach ($transactions as $transaction)
        {
            //The amount used on an authorize transaction is any amount that is captured in status success or waiting.
            //And any amount that is on a transaction of type void.
            //Any capture in error generates a void transaction but not all void transactions come from a capture error.
            if (($transaction->getTransactionType() === self::CAPTURE_ACTION
                    && $transaction->getStatus() !== self::ERROR_STATUS)
                || $transaction->getTransactionType() === self::VOID_ACTION) {
                $amountUsed += $transaction->getAmount();
            }
        }

        return $amountUsed;
    }

    /**
     * Get the payment linked to the given transaction.
     *
     * @codeCoverageIgnore
     *
     * @param int $paymentId
     *
     * @return OrderPaymentInterface
     * @throws LocalizedException
     */
    public function getPaymentLinkedToTransaction(int $paymentId): OrderPaymentInterface
    {
        return $this->orderPaymentRepository->getById($paymentId);
    }

    /**
     * Create a child capture transaction.
     *
     * @codeCoverageIgnore
     *
     * @param OrderTransactions $captureTransaction
     * @param float $amount
     * @param int $invoiceId
     *
     * @return $this
     * @throws LocalizedException
     */
    public function createChildCaptureTransaction(self $captureTransaction, float $amount, int $invoiceId): self
    {
        $childCaptureTransaction = $this->orderTransactionsFactory->create();

        $childCaptureTransaction
            ->setSessionId($captureTransaction->getSessionId())
            ->setTransactionId($captureTransaction->getTransactionId() . '_invoice_' . $invoiceId)
            ->setParentTransactionId($captureTransaction->getTransactionId())
            ->setMethod($captureTransaction->getMethod())
            ->setTransactionType(self::CHILD_CAPTURE_TYPE)
            ->setQuoteId($captureTransaction->getQuoteId())
            ->setOrderId($captureTransaction->getOrderId())
            ->setInvoiceId(null)
            ->setAmount($amount)
            ->setStatus($captureTransaction->getStatus())
            ->setParentPaymentId($captureTransaction->getParentPaymentId())
        ;

        return $this->transactionsRepository->save($childCaptureTransaction);
    }

    /**
     * @codeCoverageIgnore
     *
     * @param string $parentCaptureTransactionId
     *
     * @return $this
     * @throws LocalizedException
     */
    public function getParentCaptureFromChildCapture(string $parentCaptureTransactionId): self
    {
        return $this->transactionsRepository->getByTransactionId($parentCaptureTransactionId);
    }
}
