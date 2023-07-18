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
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order\Creditmemo;
use Psr\Log\LoggerInterface;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\PaymentFinder\allTransactionsToRefundFinder;
use Magento\Framework\Event\ManagerInterface as EventManager;

/**
 * Class RefundService
 *
 * @package UpStreamPay\Core\Model\Actions
 */
class RefundService
{
    /**
     * @param allTransactionsToRefundFinder $allTransactionsToRefundFinder
     * @param ClientInterface $client
     * @param OrderTransactions $orderTransactions
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param LoggerInterface $logger
     * @param EventManager $eventManager
     */
    public function __construct(
        private readonly allTransactionsToRefundFinder  $allTransactionsToRefundFinder,
        private readonly ClientInterface $client,
        private readonly OrderTransactions $orderTransactions,
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository,
        private readonly LoggerInterface $logger,
        private readonly EventManager $eventManager
    ) {
    }

    /**
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return InfoInterface
     * @throws LocalizedException
     */
    public function execute(InfoInterface $payment, float $amount): InfoInterface
    {
        $amountLeftToRefund = $amount;
        /** @var Creditmemo $creditmemo */
        $creditmemo = $payment->getCreditmemo();
        $invoice = $creditmemo->getInvoice();
        $captureTransactionsToRefund = $this->allTransactionsToRefundFinder->execute(
            (int)$payment->getOrder()->getEntityId(),
            (int)$invoice->getEntityId()
        );

        foreach ($captureTransactionsToRefund as $capture) {
            //$captureRefoundAmount is the max to refund on the current capture transaction.
            $captureRefoundAmount = $capture['amountToRefund'];
            /** @var OrderTransactionsInterface $captureTransaction */
            $captureTransaction = $capture['transaction'];

            if ($amountLeftToRefund > 0) {
                //If max amount to refund on transaction is less or equal than the amount to refund, refund max amount.
                //Else refund full amount.
                //IE: creditmemo is 50, max amount to refund on current capture is 10, refund 10 and move on to next
                //capture.
                $amountToRefundOnTransaction = $captureRefoundAmount <= $amountLeftToRefund ? $captureRefoundAmount : $amountLeftToRefund;
                $orderPayment = $this->orderPaymentRepository->getById($captureTransaction->getParentPaymentId());

                //Verify that we can refund this on the payment.
                //Second safety check to make sure we don't over refund. If we detect an over refund then the amount
                //to refund should be the difference between the total amount & amount refunded.
                if (($orderPayment->getAmountRefunded() + $amountToRefundOnTransaction) > $orderPayment->getAmount()) {
                    $amountToRefundOnTransaction = $orderPayment->getAmount() - $orderPayment->getAmountRefunded();
                }

                //Only refund if we have something to refund.
                if ($amountToRefundOnTransaction > 0) {
                    $body = [
                        'order' => [
                            'amount' => $payment->getOrder()->getBaseGrandTotal(),
                            'currency_code' => $payment->getOrder()->getGlobalCurrencyCode(),
                        ],
                        'amount' => $amountToRefundOnTransaction,
                    ];

                    try {
                        $refundResponse = $this->client->refund($captureTransaction->getTransactionId(), $body);
                    } catch (\Throwable $exception) {
                        //In case of a refund error, try to refund as many transactions as possible.
                        $errorMessage = sprintf(
                            'Refund for capture transaction %s for amount %s in error because %s, refund it in UpStream admin panel.',
                            $captureTransaction->getTransactionId(),
                            $exception->getMessage(),
                            $amountToRefundOnTransaction
                        );

                        $this->logger->critical($errorMessage);
                        $payment->getOrder()->addCommentToStatusHistory($errorMessage);

                        continue;
                    }
                    //Save the refund transaction in DB.
                    $refundTransaction = $this->orderTransactions->createTransactionFromResponse(
                        $refundResponse,
                        (int) $payment->getOrder()->getEntityId(),
                        (int) $payment->getOrder()->getQuoteId(),
                        (int) $captureTransaction->getParentPaymentId(),
                        (int) $invoice->getEntityId()
                    );

                    $payment->getOrder()->addCommentToStatusHistory(sprintf(
                        'Transaction %s %s for %s with amount %s in status %s',
                        $refundTransaction->getTransactionType(),
                        $refundTransaction->getTransactionId(),
                        $refundTransaction->getMethod(),
                        $refundTransaction->getAmount(),
                        $refundTransaction->getStatus()
                    ));

                    //In case of an error a manual refund must be done.
                    if ($refundTransaction->getStatus() === OrderTransactions::ERROR_STATUS) {
                        $errorMessage = sprintf(
                            'Transaction refund with ID %s for amount %s is in error, refund it in UpStream admin panel.',
                            $refundTransaction->getTransactionId(),
                            $refundTransaction->getAmount()
                        );
                        $this->logger->critical($errorMessage);
                        $payment->getOrder()->addCommentToStatusHistory($errorMessage);
                    }

                    $this->eventManager->dispatch('payment_usp_write_log', ['orderPayment' => $orderPayment]);
                    $orderPayment->setAmountRefunded($orderPayment->getAmountRefunded() + $refundTransaction->getAmount());
                    $this->orderPaymentRepository->save($orderPayment);

                    $amountLeftToRefund = $amountLeftToRefund - $refundTransaction->getAmount();
                }
            }
        }

        return $payment;
    }
}
