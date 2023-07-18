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
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
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
     * In case of child captures, the parent will be returned with the amount of the child capture.
     *
     * @param int $orderId
     * @param int $invoiceId
     *
     * @return array
     * @throws LocalizedException
     */
    public function execute(int $orderId, int $invoiceId): array
    {
        //Get all captured transactions for the invoice we are trying to refund.
        $captureTransactions = $this->allTransactionsFinder->execute(
            OrderTransactions::CAPTURE_ACTION,
            $orderId,
            OrderTransactions::SUCCESS_STATUS,
            $invoiceId
        );

        $childCaptureTransactions = $this->allTransactionsFinder->execute(
            OrderTransactions::CHILD_CAPTURE_TYPE,
            $orderId,
            OrderTransactions::SUCCESS_STATUS,
            $invoiceId
        );

        $captureTransactionsToRefund = $this->buildTransactionsToRefundArray($captureTransactions);
        $captureTransactionsWithChild = $this->buildTransactionsToRefundArray($childCaptureTransactions);

        return array_merge($captureTransactionsToRefund, $captureTransactionsWithChild);
    }

    /**
     * @param OrderTransactionsInterface[] $captureTransactions
     *
     * @return array
     * @throws LocalizedException
     */
    private function buildTransactionsToRefundArray(array $captureTransactions): array
    {
        $transactionsToRefund = [];

        //Loop all capture transactions to get all refund transactions linked to it.
        foreach ($captureTransactions as $captureTransaction) {
            $totalRefunded = 0;

            $refundTransactions = $this->orderTransactions->getRefundTransactionsFromCapture(
                $captureTransaction->getTransactionType() === OrderTransactions::CHILD_CAPTURE_TYPE
                    ? $captureTransaction->getParentTransactionId() : $captureTransaction->getTransactionId()
            );

            //Loop all refunds to increment the total refunded from the capture transaction.
            foreach ($refundTransactions as $refundTransaction) {
                $totalRefunded += $refundTransaction->getAmount();
            }

            if ($captureTransaction->getTransactionType() === OrderTransactions::CHILD_CAPTURE_TYPE) {
                //In case of a child capture what we want to refund is the direct amount of the child capture.
                $amountToRefund = $captureTransaction->getAmount();

                //In case of a child transaction type, fetch the parent.
                $captureTransaction = $this->orderTransactions->getParentCaptureFromChildCapture(
                    $captureTransaction->getParentTransactionId()
                );
            } else {
                //In case of a normal capture transaction, what we want to refund is the total capture - the total
                //refunded.
                $amountToRefund = $captureTransaction->getAmount() - $totalRefunded;
            }

            if ($captureTransaction->getAmount() > $totalRefunded) {
                //Add the capture transaction to the list of refunds with amount to refund.
                $transactionsToRefund[] = [
                    'transaction' => $captureTransaction,
                    'amountToRefund' => $amountToRefund
                ];
            }
        }

        return $transactionsToRefund;
    }
}
