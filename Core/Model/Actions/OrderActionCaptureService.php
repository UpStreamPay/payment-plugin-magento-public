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

namespace UpStreamPay\Core\Model\Actions;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\FloatComparator;
use Magento\Payment\Model\InfoInterface;
use Throwable;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Exception\CaptureErrorException;
use UpStreamPay\Core\Exception\NotEnoughFundException;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\PaymentFinder\AllTransactionsToCaptureFinder;

/**
 * Class OrderActionCaptureService
 *
 * @package UpStreamPay\Core\Model\Actions
 */
class OrderActionCaptureService
{
    private string $upStreamPaySessionId = '';

    /**
     * @param AllTransactionsToCaptureFinder $allTransactionsToCaptureFinder
     * @param OrderTransactionsRepositoryInterface $orderTransactionsRepository
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param ClientInterface $client
     * @param OrderTransactions $orderTransactions
     * @param FloatComparator $floatComparator
     */
    public function __construct(
        private readonly AllTransactionsToCaptureFinder $allTransactionsToCaptureFinder,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository,
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository,
        private readonly ClientInterface $client,
        private readonly OrderTransactions $orderTransactions,
        private readonly FloatComparator $floatComparator,
    ) {
    }

    /**
     * Capture the transactions in order to pay the invoice.
     *
     * When this is called for the first time in admin, the invoice is new & has no entity ID.
     * Because of that we set the invoice to pending payment & we trigger this after the invoice save to have an invoice
     * entity ID.
     * We MUST link every capture transaction to an invoice.
     *
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return InfoInterface
     * @throws CaptureErrorException
     * @throws LocalizedException
     * @throws NotEnoughFundException
     */
    public function execute(InfoInterface $payment, float $amount): InfoInterface
    {
        //If we have no entity ID on invoice it means it's an invoice not saved yet.
        //This means we are in the middle of creating the invoice. We will wait until the invoice is saved, so we can
        //have an invoice ID. An event will trigger this again after the invoice save.
        if ($this->magentoPaymentHasInvoice($payment)) {
            //Transaction is pending for now.
            $payment->setIsTransactionPending(true);

            return $payment;
        }

        $orderId = (int)$payment->getOrder()->getEntityId();
        $invoiceId = (int)$payment->getCreatedInvoice()->getEntityId();
        $amountPaid = 0.00;

        try {
            $transactionsToUseToPayInvoice = $this->allTransactionsToCaptureFinder->execute(
                $amount,
                $orderId,
                $invoiceId
            );
        } catch (NotEnoughFundException $exception) {
            //This should never happen but just in case.
            $errorMessage = sprintf(
                'There are been an error while trying to pay the invoice with Id %s because: %s',
                $invoiceId,
                $exception->getMessage()
            );

            throw new NotEnoughFundException($errorMessage);
        }

        foreach ($transactionsToUseToPayInvoice as $transactionToUse) {
            /** @var OrderTransactions $transaction */
            $transaction = $transactionToUse['transaction'];
            $this->setTransactionId($transaction);

            //Here we have capture or child capture transactions.
            if ($transaction->getTransactionType() !== OrderTransactions::AUTHORIZE_ACTION) {
                $amountPaid += $transaction->getAmount();

                //If the transaction is already linked to the invoice we are trying to pay, don't link it again.
                if ($transaction->getInvoiceId() === null) {
                    //Link the capture transaction to the invoice. This is very important to know what a transaction
                    //paid.
                    $transaction->setInvoiceId($invoiceId);
                    $this->orderTransactionsRepository->save($transaction);

                    //Update the amount captured on the payment method. This is very important to know what's left to
                    //capture.
                    $orderPayment = $this->orderPaymentRepository->getById($transaction->getParentPaymentId());
                    $orderPayment->setAmountCaptured($orderPayment->getAmountCaptured() + $transaction->getAmount());
                    $this->orderPaymentRepository->save($orderPayment);

                    $payment->getOrder()->addCommentToStatusHistory(sprintf(
                        'Paying invoice ID %s with transaction ID %s using method %s with amount %s.',
                        $invoiceId,
                        $transaction->getTransactionId(),
                        $transaction->getMethod(),
                        $transaction->getAmount(),
                    ));
                }
            } elseif ($transaction->getTransactionType() === OrderTransactions::AUTHORIZE_ACTION) {
                $captureTransaction = $this->captureAuthorizeTransaction(
                     $transaction,
                     $orderId,
                     $invoiceId,
                     $transactionToUse,
                     $payment
                );

                if ($captureTransaction->getStatus() === OrderTransactions::SUCCESS_STATUS) {
                    $amountPaid += $captureTransaction->getAmount();

                    $payment->getOrder()->addCommentToStatusHistory(sprintf(
                        'Paying invoice ID %s with transaction ID %s using method %s with amount %s.',
                        $invoiceId,
                        $captureTransaction->getTransactionId(),
                        $captureTransaction->getMethod(),
                        $captureTransaction->getAmount(),
                    ));
                } elseif ($captureTransaction->getStatus() === OrderTransactions::ERROR_STATUS) {
                    $errorMessage = sprintf(
                        'Capture transaction %s with method %s for amount %s is in error for invoice %s.',
                        $captureTransaction->getTransactionId(),
                        $captureTransaction->getMethod(),
                        $captureTransaction->getAmount(),
                        $invoiceId
                    );

                    throw new CaptureErrorException($errorMessage);
                } elseif ($captureTransaction->getStatus() === OrderTransactions::WAITING_STATUS) {
                    $payment->setIsTransactionPending(true);

                    //If we detect a waiting, it's very important to stop so that wait for the webhook before
                    // continuing. If we don't then the next transaction could be an error, then we would have a
                    // transaction in waiting tha't we might have to refund but that we can't refund. So to keep the
                    //process simple, we stop for each waiting on the capture.
                    break;
                }
            }
        }

        //To avoid issue when comparing floats, use built-in magento feature (it uses an epsilon of 0.00001).
        //If both amount match & there has been no error.
        if ($this->floatComparator->equal($amountPaid, $amount)) {
            $payment
                ->setTransactionId($this->upStreamPaySessionId . '-' . $invoiceId)
                ->setIsTransactionClosed(false)
                ->setIsTransactionPending(false)
                ->setIsTransactionApproved(true)
                ->setCurrencyCode($payment->getOrder()->getGlobalCurrencyCode())
            ;
        }

        return $payment;
    }

    /**
     * Check if the Magento payment has an invoice linked to it.
     *
     * @param InfoInterface $payment
     *
     * @return bool
     */
    private function magentoPaymentHasInvoice(InfoInterface $payment): bool
    {
        //If we have no entity ID on invoice it means it's an invoice not saved yet.
        //This means we are in the middle of creating the invoice. We will wait until the invoice is saved, so we can
        //have an invoice ID. An event will trigger this again after the invoice save.
        return $payment->getCreatedInvoice() === null || $payment->getCreatedInvoice()->getEntityId() === null;
    }

    /**
     * Set the transaction ID used for the magento transaction.
     *
     * @param OrderTransactionsInterface $transaction
     *
     * @return void
     */
    private function setTransactionId(OrderTransactionsInterface $transaction): void
    {
        if ($this->upStreamPaySessionId === '') {
            $this->upStreamPaySessionId = $transaction->getSessionId();
        }
    }

    /**
     * Capture the given authorize transaction & save it in DB.
     *
     * @param OrderTransactionsInterface $transaction
     * @param int $orderId
     * @param int $invoiceId
     * @param array $transactionToUse
     * @param InfoInterface $payment
     *
     * @return OrderTransactionsInterface
     * @throws CaptureErrorException
     */
    private function captureAuthorizeTransaction(
        OrderTransactionsInterface $transaction,
        int $orderId,
        int $invoiceId,
        array $transactionToUse,
        InfoInterface $payment
    ): OrderTransactionsInterface
    {
        $body = [
            'order' => [
                'amount' => $payment->getOrder()->getBaseGrandTotal(),
                'currency_code' => $payment->getOrder()->getGlobalCurrencyCode(),
            ],
            'amount' => $transactionToUse['amountToCapture'],
        ];

        try {
            $response = $this->client->capture($transaction->getTransactionId(), $body);
            $captureTransaction = $this->orderTransactions->createTransactionFromResponse(
                $response,
                $orderId,
                $transaction->getQuoteId(),
                $transaction->getParentPaymentId(),
                $invoiceId
            );

            //Update the amount captured on the payment method. This is very important to know what's left to
            //capture.
            $orderPayment = $this->orderPaymentRepository->getById($captureTransaction->getParentPaymentId());
            $orderPayment->setAmountCaptured(
                $orderPayment->getAmountCaptured() + $captureTransaction->getAmount()
            );
            $this->orderPaymentRepository->save($orderPayment);
        } catch (Throwable $exception) {
            $errorMessage = sprintf(
                'Error while trying to capture transaction %s for amount %s for invoice %s because %s',
                $transaction->getTransactionId(),
                $transactionToUse['amountToCapture'],
                $invoiceId,
                $exception->getMessage()
            );

            throw new CaptureErrorException($errorMessage);
        }

        return $captureTransaction;
    }
}
