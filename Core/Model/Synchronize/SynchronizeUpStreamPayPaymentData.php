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

namespace UpStreamPay\Core\Model\Synchronize;

use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Api\PaymentMethodRepositoryInterface;
use UpStreamPay\Core\Exception\NoPaymentMethodFoundException;
use UpStreamPay\Core\Model\OrderPayment;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Config\Source\Debug;

/**
 * Class SynchronizeUpStreamPayPaymentData
 *
 * @package UpStreamPay\Core\Model\Synchronize
 */
class SynchronizeUpStreamPayPaymentData
{
    /**
     * @param OrderPayment $orderPayment
     * @param OrderTransactions $orderTransactions
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param OrderTransactionsRepositoryInterface $orderTransactionsRepository
     * @param PaymentMethodRepositoryInterface $paymentMethodRepository
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly OrderPayment $orderPayment,
        private readonly OrderTransactions $orderTransactions,
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository,
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Synchronize the DB with UpStream Pay response data.
     * Create or update based on the response.
     *
     * @param array $orderTransactionsResponse
     * @param int $orderId
     * @param int $quoteId
     * @param int $paymentId
     *
     * @return void
     * @throws LocalizedException
     * @throws NoPaymentMethodFoundException
     */
    public function execute(array $orderTransactionsResponse, int $orderId, int $quoteId, int $paymentId): void
    {
        $parentPaymentId = null;

        if ($orderId !== 0) {
            foreach ($orderTransactionsResponse as $orderTransactionResponse) {
                $this->log($orderTransactionResponse, $orderId);

                //Create a row in payment table for each original transaction, it means transactions without
                //a parent_id.
                if (!isset($orderTransactionResponse['parent_transaction_id'])) {
                    $orderPayment = $this->orderPaymentRepository->getByDefaultTransactionId(
                        $orderTransactionResponse['id']
                    );

                    //We can only create this, the payment methods should never be updated unless we are doing a capture
                    //Or refund then the amount captured or refunded will be updated, but just that.
                    if (!$orderPayment || !$orderPayment->getEntityId()
                        && $orderTransactionResponse['transaction_id'] !== $orderPayment->getDefaultTransactionId()
                    ) {
                        $paymentMethodType = $this->getPaymentMethodTypeFromResponse(
                            $orderTransactionResponse,
                            $orderId
                        );

                        //Create.
                        $upStreamPayPayment = $this->orderPayment->createPaymentFromResponse(
                            $orderTransactionResponse,
                            $orderId,
                            $quoteId,
                            $paymentId,
                            $paymentMethodType
                        );

                        $parentPaymentId = $upStreamPayPayment->getEntityId();
                    }
                }

                $orderTransaction = $this->orderTransactionsRepository->getByTransactionId(
                    $orderTransactionResponse['id']
                );

                $this->createTransaction(
                    $orderTransaction,
                    $orderTransactionResponse,
                    $orderId,
                    $quoteId,
                    $parentPaymentId
                );
            }
        }
    }

    /**
     * Get the payment method type.
     *
     * @param array $orderTransactionResponse
     * @param int $orderId
     *
     * @return string
     * @throws LocalizedException
     * @throws NoPaymentMethodFoundException
     */
    private function getPaymentMethodTypeFromResponse(array $orderTransactionResponse, int $orderId): string
    {
        $paymentMethodName = $orderTransactionResponse['partner'] . ' / ' . $orderTransactionResponse['method'];
        $paymentMethod = $this->paymentMethodRepository->getByMethod($paymentMethodName);

        if ($paymentMethod && $paymentMethod->getEntityId()) {
            return $paymentMethod->getType();
        }

        $errorMessage = sprintf(
            'Payment method %s not found in DB when creationg transaction for order with ID %s.',
            $paymentMethodName,
            $orderId
        );

        $this->logger->critical($errorMessage);

        throw new NoPaymentMethodFoundException($errorMessage);
    }

    /**
     * @param array $orderTransactionResponse
     * @param int $orderId
     *
     * @return void
     */
    private function log(array $orderTransactionResponse, int $orderId): void
    {
        if ($this->config->getDebugMode() === Debug::SIMPLE_VALUE
            || $this->config->getDebugMode() === Debug::DEBUG_VALUE) {
            $this->logger->debug(sprintf('Creating transaction for order with ID %s', $orderId));
            if ($orderTransactionResponse) {
                $this->logger->debug(print_r($orderTransactionResponse, true));
            }
        }
    }

    /**
     * @param OrderTransactionsInterface $orderTransaction
     * @param array $orderTransactionResponse
     * @param int $orderId
     * @param int $quoteId
     * @param null|int $parentPaymentId
     *
     * @return void
     * @throws LocalizedException
     */
    private function createTransaction(
        OrderTransactionsInterface $orderTransaction,
        array $orderTransactionResponse,
        int $orderId,
        int $quoteId,
        ?int $parentPaymentId
    ): void
    {
        if (!$orderTransaction || !$orderTransaction->getEntityId()) {
            //Create.
            $this->orderTransactions->createTransactionFromResponse(
                $orderTransactionResponse,
                $orderId,
                $quoteId,
                $parentPaymentId
            );
        }
    }
}
