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

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\FloatComparator;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
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
    /**
     * @param AllTransactionsToCaptureFinder $allTransactionsToCaptureFinder
     * @param OrderTransactionsRepositoryInterface $orderTransactionsRepository
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param LoggerInterface $logger
     * @param ClientInterface $client
     * @param OrderTransactions $orderTransactions
     * @param FloatComparator $floatComparator
     */
    public function __construct(
        private readonly AllTransactionsToCaptureFinder $allTransactionsToCaptureFinder,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository,
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository,
        private readonly LoggerInterface $logger,
        private readonly ClientInterface $client,
        private readonly OrderTransactions $orderTransactions,
        private readonly FloatComparator $floatComparator,
        private readonly InvoiceRepositoryInterface $invoiceRepository
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
     * @throws GuzzleException
     * @throws JsonException
     * @throws LocalizedException
     */
    public function execute(InfoInterface $payment, float $amount): InfoInterface
    {
        //If we have no entity ID on invoice it means it's an invoice not saved yet.
        //This means we are in the middle of creating the invoice. We will wait until the invoice is saved, so we can
        //have an invoice ID. An event will trigger this again after the invoice save.
        if ($payment->getCreatedInvoice() === null || $payment->getCreatedInvoice()->getEntityId() === null) {
            //Transaction is pending for now.
            $payment->setIsTransactionPending(true);

            return $payment;
        }

        $orderId = (int)$payment->getOrder()->getEntityId();
        $invoiceId = (int)$payment->getCreatedInvoice()->getEntityId();
        $amountPaid = 0.00;
        $upStreamPaySessionId = '';
        $atLeastOneCaptureInError = false;

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

            $this->logger->critical($errorMessage, ['exception' => $exception->getTraceAsString()]);
            $payment->getOrder()->addCommentToStatusHistory($errorMessage);
            $payment->setIsTransactionDenied(true);



            return $payment;
        }

        foreach ($transactionsToUseToPayInvoice as $transactionToUse) {
            /** @var OrderTransactions $transaction */
            $transaction = $transactionToUse['transaction'];

            if ($upStreamPaySessionId === '') {
                $upStreamPaySessionId = $transaction->getSessionId();
            }

            //Here we have capture or child capture transactions.
            if ($transaction->getTransactionType() !== OrderTransactions::AUTHORIZE_ACTION) {
                $amountPaid += $transaction->getAmount();

                //Link the capture transaction to the invoice. This is very important to know what a transaction paid.
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
            } elseif ($transaction->getTransactionType() == OrderTransactions::AUTHORIZE_ACTION
                && !$atLeastOneCaptureInError) {
                //We have to capture any authorize transaction we have if no prvious capture has failed.
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
                } catch (\Throwable $exception) {
                    $errorMessage = sprintf(
                        'Error while trying to capture authorize transaction %s for amount %s for invoice %s',
                        $transaction->getTransactionId(),
                        $transactionToUse['amountToCapture'],
                        $invoiceId
                    );
                    $this->logger->critical($errorMessage, ['exception' => $exception->getTraceAsString()]);
                    $payment->getOrder()->addCommentToStatusHistory($errorMessage);

                    $atLeastOneCaptureInError = true;
                }

                $atLeastOneCaptureInError = true;

                //@TODO handle waiting.
                if (!$atLeastOneCaptureInError
                    && $captureTransaction->getStatus() === OrderTransactions::SUCCESS_STATUS) {
                    $amountPaid += $captureTransaction->getAmount();

                    $payment->getOrder()->addCommentToStatusHistory(sprintf(
                        'Paying invoice ID %s with transaction ID %s using method %s with amount %s.',
                        $invoiceId,
                        $captureTransaction->getTransactionId(),
                        $captureTransaction->getMethod(),
                        $captureTransaction->getAmount(),
                    ));
                } elseif ($captureTransaction->getStatus() === OrderTransactions::ERROR_STATUS) {
                    $payment->getOrder()->addCommentToStatusHistory(sprintf(
                        'Capture transaction %s with method %s for amount %s is in error for invoice %s.',
                        $captureTransaction->getTransactionId(),
                        $captureTransaction->getMethod(),
                        $captureTransaction->getAmount(),
                        $invoiceId
                    ));

                    $atLeastOneCaptureInError = true;
                }
            }
        }

        //@TODO handle the success better & add different cases based if we have errors or waiting.
        //To avoid issue when comparing floats, use built in magento feature (it uses an epsilon of 0.00001).
        //If both amount match & there has been no error.
        if ($this->floatComparator->equal($amountPaid, $amount) && $atLeastOneCaptureInError === false) {
            $payment
                ->setTransactionId($upStreamPaySessionId . '-' . $invoiceId)
                ->setIsTransactionClosed(false)
                ->setIsTransactionPending(false)
                ->setIsTransactionApproved(true)
                ->setCurrencyCode($payment->getOrder()->getGlobalCurrencyCode())
            ;
        } elseif ($atLeastOneCaptureInError === true) {
            //We have at least one capture in error.
            //We must refund every capture linked to the invoice & void the correct amount on the authorizes.
            $payment->getCreatedInvoice()->cancel();
            $this->invoiceRepository->save($payment->getCreatedInvoice());
        }

        return $payment;
    }
}
