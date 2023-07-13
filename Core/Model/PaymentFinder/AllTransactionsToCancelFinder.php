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
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;
use UpStreamPay\Core\Model\OrderTransactions;

/**
 * Class AllTransactionsToCancelFinder
 *
 * @package UpStreamPay\Core\Model\PaymentFinder
 */
class AllTransactionsToCancelFinder
{
    /**
     * @param AllTransactionsFinder $allTransactionsFinder
     * @param OrderTransactions $orderTransactions
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param FloatComparator $floatComparator
     */
    public function __construct(
        private readonly AllTransactionsFinder $allTransactionsFinder,
        private readonly OrderTransactions $orderTransactions,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly FloatComparator $floatComparator,
    ) {
    }

    /**
     * Find all the transactions to cancel for the given order.
     *
     * A transaction to cancel is a transaction that:
     * - is not linked to a paid invoice (thus not linked to a creditmemo).
     * - is a capture transaction with amount left to refund.
     * - is an authorize transaction with amount left to void.
     *
     * @param int $orderId
     *
     * @return array
     * @throws LocalizedException
     */
    public function execute(int $orderId): array
    {
        $transactions = [];

        $captureTransactions = $this->allTransactionsFinder->execute(
            OrderTransactions::CAPTURE_ACTION,
            $orderId,
            OrderTransactions::SUCCESS_STATUS
        );

        foreach ($captureTransactions as $captureTransaction) {
            //Check if the capture is linked to an invoice & if so, check if the invoice is paid or not.
            if ($captureTransaction->getInvoiceId() !== null) {
                $invoice = $this->invoiceRepository->get($captureTransaction->getInvoiceId());

                if ($invoice->getState() === Invoice::STATE_PAID) {
                    //If the capture is linked to a paid invoice then we don't need to refund it.
                    continue;
                }
            }

            $totalRefunded = 0.00;
            $amountUsedOnInvoice = 0.0;
            $refundTransactions = $this->orderTransactions->getRefundTransactionsFromCapture(
                $captureTransaction->getTransactionId()
            );

            //Loop all refunds to increment the total refunded from the capture transaction.
            foreach ($refundTransactions as $refundTransaction) {
                $totalRefunded += $refundTransaction->getAmount();
            }

            //If the capture has already been refunded in full.
            if ($this->floatComparator->equal($totalRefunded, $captureTransaction->getAmount())) {
                continue;
            }

            //The max we can refund, based on what has been refunded before.
            $maxToRefund = $captureTransaction->getAmount() - $totalRefunded;

            //Get all child captures.
            $childCaptures = $this->orderTransactions->getChildCapturesTransactionsFromCapture(
                $captureTransaction->getTransactionId()
            );

            foreach ($childCaptures as $childCapture) {
                //Check if the capture is linked to an invoice & if so, check if the invoice is paid or not.
                if ($childCapture->getInvoiceId() !== null) {
                    $invoice = $this->invoiceRepository->get($childCapture->getInvoiceId());

                    //Child capture was used to pay an invoice that is in paid state, so we can't refund it.
                    if ($invoice->getState() === Invoice::STATE_PAID) {
                        $amountUsedOnInvoice += $childCapture->getAmount();
                    }
                }
            }

            //What we should refund, what has not been used on a paid invoice.
            $amountToRefundOnCapture = $captureTransaction->getAmount() - $amountUsedOnInvoice;

            //Check if the amount to refund is not greater than the max to refund.
            if ($this->floatComparator->greaterThan($amountToRefundOnCapture, $maxToRefund)) {
                $amountToRefundOnCapture = $maxToRefund;
            }

            //Add capture to the list of transaction, with the amount to refund.
            $transactions[] = [
                'transaction' => $captureTransaction,
                'amounToRefund' => $amountToRefundOnCapture
            ];
        }

        $authorizeTransactions = $this->allTransactionsFinder->execute(
            OrderTransactions::AUTHORIZE_ACTION,
            $orderId,
            OrderTransactions::SUCCESS_STATUS
        );

        foreach ($authorizeTransactions as $authorizeTransaction) {
            $amountUsedOnCapture = 0.00;
            $totalVoided = 0.00;

            $voidTransactions = $this->orderTransactions->getVoidTransactionsFromAuthorize(
                $authorizeTransaction->getTransactionId()
            );

            //Calculate the total voided on the give authorize transaction.
            foreach ($voidTransactions as $voidTransaction) {
                $totalVoided += $voidTransaction->getAmount();
            }

            //The authorized transaction has been voided in full, no need to go any further.
            if ($this->floatComparator->equal($totalVoided, $authorizeTransaction->getAmount())) {
                continue;
            }

            //The max we can refund, based on what has been refunded before.
            $maxToVoid = $authorizeTransaction->getAmount() - $totalVoided;

            $captureTransactions = $this->orderTransactions->getCaptureTransactionsFromAuthorize(
                $authorizeTransaction->getTransactionId()
            );

            foreach ($captureTransactions as $captureTransaction) {
                $amountUsedOnCapture += $captureTransaction->getAmount();
            }

            //If the authorized transactions has been captured in full, no need to go any further.
            if ($this->floatComparator->equal($amountUsedOnCapture, $authorizeTransaction->getAmount())) {
                continue;
            }

            //What we should void, what has not been captured.
            $amountToVoidOnAuthorize = $authorizeTransaction->getAmount() - $amountUsedOnCapture;

            //Check if the amount to refund is not greater than the max to refund.
            if ($this->floatComparator->greaterThan($amountToVoidOnAuthorize, $maxToVoid)) {
                $amountToVoidOnAuthorize = $maxToVoid;
            }

            //Add capture to the list of transaction, with the amount to refund.
            $transactions[] = [
                'transaction' => $authorizeTransaction,
                'amounToRefund' => $amountToVoidOnAuthorize
            ];
        }

        return $transactions;
    }
}
