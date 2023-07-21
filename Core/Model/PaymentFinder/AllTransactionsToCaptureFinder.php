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
use Magento\Framework\Math\FloatComparator;
use UpStreamPay\Core\Exception\NotEnoughFundException;
use UpStreamPay\Core\Model\OrderTransactions;

/**
 * Class AllTransactionsToCaptureFinder
 *
 * @package UpStreamPay\Core\Model\PaymentFinder
 */
class AllTransactionsToCaptureFinder
{
    /**
     * @param OrderTransactions $orderTransactions
     * @param AllTransactionsFinder $allTransactionsFinder
     * @param FloatComparator $floatComparator
     */
    public function __construct(
        private readonly OrderTransactions $orderTransactions,
        private readonly AllTransactionsFinder $allTransactionsFinder,
        private readonly FloatComparator $floatComparator
    ) {
    }

    /**
     * Retrieve the transactions to use in order to pay the invoice.
     *
     * This will retrieve the list of authorize, capture & child capture transactions to use with the amount used each
     * time on the transaction.
     *
     * No capture is done here, just retrieving the transactions to use.
     * In case of a partial capture on a capture transaction, this will also create the child transaction to use.
     *
     * @param float $amount
     * @param int $orderId
     * @param int $invoiceId
     *
     * @return array
     * @throws LocalizedException
     * @throws NotEnoughFundException
     */
    public function execute(float $amount, int $orderId, int $invoiceId): array
    {
        $captureTransactionsUsed = [];
        $authorizeTransactionsUsed = [];
        $amountLeftToCapture = $amount;
        $captureTransactions = $this->allTransactionsFinder->execute(
            OrderTransactions::CAPTURE_ACTION,
            $orderId,
            OrderTransactions::SUCCESS_STATUS
        );

        foreach ($captureTransactions as $captureTransaction) {
            //When there is nothing left to capture we can exit this loop.
            if ($this->floatComparator->equal($amountLeftToCapture, 0.00)) {
                break;
            }

            if ($captureTransaction->getInvoiceId() !== null) {
                if ($captureTransaction->getInvoiceId() === $invoiceId) {
                    $captureTransactionsUsed[] = [
                        'transaction' => $captureTransaction,
                        'amountToCapture' => $captureTransaction->getAmount()
                    ];

                    $amountLeftToCapture = $amountLeftToCapture - $captureTransaction->getAmount();
                }

                continue;
            }

            $amountCaptured = $this->orderTransactions->getAmountCapturedOnChildCapturesTransaction(
                $captureTransaction->getTransactionId()
            );

            //This is the max amount we can capture on the transaction based on what has been captured on child
            //transactions so far.
            //A capture can be split into several sub captures used on different invoices.
            $maxAmountToCapture = $captureTransaction->getAmount() - $amountCaptured;

            //This means that there is nothing left to capture on this transaction or that the capture has a child
            //capture linked to the invoice we are paying.
            if ($this->floatComparator->equal($maxAmountToCapture, 0.00)) {
                $childCaptureTransactions = $this->orderTransactions->getChildCapturesTransactionsFromCapture(
                    $captureTransaction->getTransactionId()
                );

                //Loop all the child captures to check if we indeed have a child transaction used to pay the current
                //invoice (we should have one).
                foreach ($childCaptureTransactions as $childCaptureTransaction) {
                    if ($childCaptureTransaction->getInvoiceId() === $invoiceId) {
                        $captureTransactionsUsed[] = [
                            'transaction' => $childCaptureTransaction,
                            'amountToCapture' => $childCaptureTransaction->getAmount()
                        ];

                        $amountLeftToCapture = $amountLeftToCapture - $childCaptureTransaction->getAmount();
                    }
                }

                //Because this capture has nothing left to capture available, even if it has no child to use, move on
                //to the next capture.
                continue;
            }

            //If the max amount available to capture is <= to the amount to capture, then what we must capture is the
            //max allowed on transaction. Otherwise, refund the amount left to capture.
            $amountToCaptureOnTransaction = $maxAmountToCapture <= $amountLeftToCapture ? $maxAmountToCapture : $amountLeftToCapture;
            //This is the amount left that we can capture on the payment method.
            //It should be the same as the amount calculated before.
            $amountLeftToCaptureOnPayment = $this->orderTransactions->getAmountLeftToCaptureOnTransaction(
                $captureTransaction->getParentPaymentId()
            );

            //This is a second safety check, we calculated before the amount left to capture based on the transactions
            //made. If the amount calculated is > to the amount left on the payment method, then use what's on the
            //payment method.
            //$amountToCaptureOnTransaction can be inferior to the amount left to capture, this is normal because we
            //can invoice partially. The only important thing is to make sure it's not superior to what we can capture.
            if ($amountToCaptureOnTransaction > $amountLeftToCaptureOnPayment) {
                $amountToCaptureOnTransaction = $amountLeftToCaptureOnPayment;
            }

            $amountLeftToCapture = $amountLeftToCapture - $amountToCaptureOnTransaction;

            if ($amountToCaptureOnTransaction < $captureTransaction->getAmount() && $amountToCaptureOnTransaction > 0) {
                //Create child capture transaction because we are not using the full amount of the original capture
                //transaction & we need to link 1 capture to 1 invoice / 1 refund.
                $childCaptureTransaction = $this->orderTransactions->createChildCaptureTransaction(
                    $captureTransaction,
                    $amountToCaptureOnTransaction,
                    $invoiceId
                );

                $captureTransactionsUsed[] = [
                    'transaction' => $childCaptureTransaction,
                    'amountToCapture' => $childCaptureTransaction->getAmount()
                ];
            } else {
                $captureTransactionsUsed[] = [
                    'transaction' => $captureTransaction,
                    'amountToCapture' => $captureTransaction->getAmount()
                ];
            }
        }

        //It means that we still have an amount to capture
        if ($amountLeftToCapture > 0) {
            //Get all authorize transactions on the order.
            $authorizeTransactions = $this->allTransactionsFinder->execute(
                OrderTransactions::AUTHORIZE_ACTION,
                $orderId,
                OrderTransactions::SUCCESS_STATUS
            );

            foreach ($authorizeTransactions as $authorizeTransaction) {
                //When there is nothing left to capture we can exit this loop.
                if ($this->floatComparator->equal($amountLeftToCapture, 0.00)) {
                    break;
                }

                $amountUsedOnTransaction = $this->orderTransactions->getAmountUsedOnAuthorizeTransaction(
                    $authorizeTransaction->getTransactionId()
                );

                //This is the max amount we can capture on the transaction based on what has been captured or
                //voided.
                //An authorize can be captured or voided.
                $maxAmountToCapture = $authorizeTransaction->getAmount() - $amountUsedOnTransaction;

                //In case the authorized transaction has been used completely there is nothing left to capture.
                if ($this->floatComparator->equal($maxAmountToCapture, 0.00)) {
                    continue;
                }

                //If the max amount available to capture is <= to the amount to capture, then what we must capture is
                //the max allowed on transaction. Otherwise, refund the amount left to capture.
                $amountToCaptureOnTransaction = $maxAmountToCapture <= $amountLeftToCapture ? $maxAmountToCapture : $amountLeftToCapture;

                //This is the amount left that we can capture on the payment method.
                //It should be the same as the amount calculated before.
                $amountLeftToCaptureOnPayment = $this->orderTransactions->getAmountLeftToCaptureOnTransaction(
                    $authorizeTransaction->getParentPaymentId()
                );

                //This is a second safety check, we calculated before the amount left to capture based on the
                //transactions made. If the amount calculated is > to the amount left on the payment method, then use
                //what's on the payment method.
                //$amountToCaptureOnTransaction can be inferior to the amount left to capture, this is normal because we
                //can invoice partially. The only important thing is to make sure it's not > to what we can capture.
                if ($amountToCaptureOnTransaction > $amountLeftToCaptureOnPayment) {
                    $amountToCaptureOnTransaction = $amountLeftToCaptureOnPayment;
                }

                $amountLeftToCapture = $amountLeftToCapture - $amountToCaptureOnTransaction;

                $authorizeTransactionsUsed[] = [
                    'transaction' => $authorizeTransaction,
                    'amountToCapture' => $amountToCaptureOnTransaction
                ];
            }
        }

        if ($amountLeftToCapture > 0) {
            throw new NotEnoughFundException('There is not enough fund on transactions to pay the invoice.');
        }

        return array_merge($captureTransactionsUsed, $authorizeTransactionsUsed);
    }
}
