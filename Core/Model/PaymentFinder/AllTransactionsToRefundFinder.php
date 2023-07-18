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

namespace UpStreamPay\Core\Model\PaymentFinder;

use Magento\Framework\Exception\LocalizedException;
use UpStreamPay\Core\Model\OrderTransactions;

/**
 * Class AllTransactionsToRefundFinder
 *
 * @package UpStreamPay\Core\Model\PaymentFinder
 */
class AllTransactionsToRefundFinder
{
    /**
     * @param AllTransactionsFinder $allTransactionsFinder
     * @param OrderTransactions $orderTransactions
     */
    public function __construct(
        private readonly AllTransactionsFinder $allTransactionsFinder,
        private readonly OrderTransactions $orderTransactions
    ) {
    }

    /**
     * Get all capture transactions to refund with maximum amount to refund on each of them.
     *
     * @param int $orderId
     * @param int $invoiceId
     *
     * @return array
     * @throws LocalizedException
     */
    public function execute(int $orderId, int $invoiceId): array
    {
        $transactionsToRefund = [];

        //Get all captured transactions for the invoice we are trying to refund.
        $captureTransactions = $this->allTransactionsFinder->execute(
            OrderTransactions::CAPTURE_ACTION,
            $orderId,
            OrderTransactions::SUCCESS_STATUS,
            $invoiceId
        );

        //Loop all capture transactions to get all refund transactions linked to it.
        foreach ($captureTransactions as $captureTransaction) {
            $totalRefunded = 0;
            $refundTransactions = $this->orderTransactions
                ->getRefundTransactionsFromCapture($captureTransaction->getTransactionId())
            ;

            //Loop all refunds to increment the total refunded from the capture transaction.
            foreach ($refundTransactions as $refundTransaction) {
                $totalRefunded += $refundTransaction->getAmount();
            }

            if ($captureTransaction->getAmount() > $totalRefunded) {
                //Add the capture transaction to the list of refunds with amount to refund.
                $transactionsToRefund[] = [
                    'transaction' => $captureTransaction,
                    'amountToRefund' => $captureTransaction->getAmount() - $totalRefunded
                ];
            }
        }

        return $transactionsToRefund;
    }
}
