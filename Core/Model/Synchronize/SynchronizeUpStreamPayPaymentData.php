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

namespace UpStreamPay\Core\Model\Synchronize;

use Magento\Framework\Exception\LocalizedException;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Model\OrderPayment;
use UpStreamPay\Core\Model\OrderTransactions;

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
     */
    public function __construct(
        private readonly OrderPayment $orderPayment,
        private readonly OrderTransactions $orderTransactions,
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository
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
     */
    public function execute(array $orderTransactionsResponse, int $orderId, int $quoteId, int $paymentId): void
    {
        $parentPaymentId = null;

        foreach ($orderTransactionsResponse as $orderTransactionResponse) {
            //Create a row in payment table for each original transaction, it means transactions without a parent_id.
            if (!isset($orderTransactionResponse['parent_transaction_id'])) {
                $orderPayment = $this->orderPaymentRepository->getByDefaultTransactionId($orderTransactionResponse['id']);

                //We can only create this, the payment methods should never be updated unless we are doing a capture
                //Or refund then the amount captured or refunded will be updated, but just that.
                if (!$orderPayment || !$orderPayment->getEntityId()
                    && $orderTransactionResponse['transaction_id'] !== $orderPayment->getDefaultTransactionId()
                ) {
                    //Create.
                    $upStreamPayPayment = $this->orderPayment->createPaymentFromResponse(
                        $orderTransactionResponse,
                        $orderId,
                        $quoteId,
                        $paymentId
                    );

                    $parentPaymentId = $upStreamPayPayment->getEntityId();
                }
            }

            $orderTransaction = $this->orderTransactionsRepository->getByTransactionId($orderTransactionResponse['id']);

            if ($orderTransaction && $orderTransaction->getEntityId()) {
                //Update.
                //@TODO update in case of action on the transaction (should only update the status).
            } else {
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
}
