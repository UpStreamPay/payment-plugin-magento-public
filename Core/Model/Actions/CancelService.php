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

use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Config\Source\Debug;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\PaymentFinder\AllTransactionsToCancelFinder;

/**
 * Class CancelService
 *
 * @package UpStreamPay\Core\Model\Actions
 */
class CancelService
{
    /**
     * @param ClientInterface $client
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param AllTransactionsToCancelFinder $allTransactionsToCancelFinder
     * @param OrderTransactions $orderTransactions
     * @param LoggerInterface $logger
     * @param EventManager $eventManager
     * @param Config $config
     * @param OrderManagementInterface $orderManagement
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository,
        private readonly AllTransactionsToCancelFinder $allTransactionsToCancelFinder,
        private readonly OrderTransactions $orderTransactions,
        private readonly LoggerInterface $logger,
        private readonly EventManager $eventManager,
        private readonly Config $config,
        private readonly OrderManagementInterface $orderManagement
    ) {
    }

    /**
     * Refund & void the transactions not used in a paid invoice.
     * Then cancel the invoice & save it.
     *
     * @param OrderInterface $order
     * @param bool $cancelOrder
     *
     * @return void
     * @throws LocalizedException
     */
    public function execute(OrderInterface $order, bool $cancelOrder = true): void
    {
        $transactionsToCancel = $this->allTransactionsToCancelFinder->execute((int) $order->getEntityId());

        foreach ($transactionsToCancel as $transactionToCancel) {
            /** @var OrderTransactionsInterface $transaction */
            $transaction = $transactionToCancel['transaction'];
            $amounToCancel = $transactionToCancel['amountToCancel'];

            $body = [
                'order' => [
                    'amount' => $order->getBaseGrandTotal(),
                    'currency_code' => $order->getGlobalCurrencyCode(),
                ],
                'amount' => $amounToCancel,
            ];

            $orderPayment = $this->orderPaymentRepository->getById($transaction->getParentPaymentId());

            if ($transaction->getTransactionType() === OrderTransactions::CAPTURE_ACTION) {
                //Verify that we can refund this on the payment.
                //Second safety check to make sure we don't over refund. If we detect an over refund then the amount
                //to refund should be the difference between the total amount & amount refunded.
                if (($orderPayment->getAmountRefunded() + $amounToCancel) > $orderPayment->getAmount()) {
                    $body['amount'] = $orderPayment->getAmount() - $orderPayment->getAmountRefunded();
                }

                try {
                    $refundResponse = $this->client->refund($transaction->getTransactionId(), $body);
                } catch (Throwable $exception) {
                    //In case of a refund error, try to refund as many transactions as possible.
                    $errorMessage = sprintf(
                        'Refund for capture transaction %s for amount %s in error because %s, refund it in UpStream admin panel.',
                        $transaction->getTransactionId(),
                        $exception->getMessage(),
                        $body['amount']
                    );

                    $this->logger->critical($errorMessage);
                    $order->addCommentToStatusHistory($errorMessage);

                    continue;
                }

                $refundTransaction = $this->orderTransactions->createTransactionFromResponse(
                    $refundResponse,
                    $transaction->getOrderId(),
                    $transaction->getQuoteId(),
                    $transaction->getParentPaymentId(),
                );

                $order->addCommentToStatusHistory(sprintf(
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
                    $order->addCommentToStatusHistory($errorMessage);
                }

                $orderPayment->setAmountRefunded($orderPayment->getAmountRefunded() + $refundTransaction->getAmount());
                $this->orderPaymentRepository->save($orderPayment);
                $this->eventManager->dispatch('payment_usp_write_log', ['orderPayment' => $orderPayment]);
            } elseif ($transaction->getTransactionType() === OrderTransactions::AUTHORIZE_ACTION) {
                try {
                    //Void from API.
                    $voidResponse = $this->client->void($transaction->getTransactionId(), $body);
                } catch (Throwable $exception) {
                    //an error on the API call has been found, don't block the process. This is not a standard error
                    //& it should not happen most of the time. In case it does, try to void as many transactions as
                    //we can.
                    $errorMessage = sprintf(
                        'Void for authorize transaction %s for amount %s is in error because %s, void it in UpStream admin panel.',
                        $transaction->getTransactionId(),
                        $exception->getMessage(),
                        $body['amount']
                    );

                    $this->logger->critical($errorMessage);
                    $order->addCommentToStatusHistory($errorMessage);

                    continue;
                }

                //Save the void transaction in DB.
                $voidTransaction = $this->orderTransactions->createTransactionFromResponse(
                    $voidResponse,
                    $transaction->getOrderId(),
                    $transaction->getQuoteId(),
                    $transaction->getParentPaymentId(),
                );

                if ($this->config->getDebugMode() === Debug::SIMPLE_VALUE || $this->config->getDebugMode() === Debug::DEBUG_VALUE) {
                    $this->logger->debug(
                        sprintf(
                            'Payment denied for order %s, void transaction response:',
                            $order->getEntityId()
                        )
                    );

                    $this->logger->debug(print_r($voidResponse, true));
                }

                $order->addCommentToStatusHistory(sprintf(
                    'Voiding authorize transaction %s for payment %s with void transaction %s',
                    $transaction->getTransactionId(),
                    $transaction->getMethod(),
                    $voidTransaction->getTransactionId()
                ));
            }
        }

        if ($cancelOrder) {
            //Now cancel the order, whatever has been invoiced & paid won't be canceled.
            $this->orderManagement->cancel($order->getEntityId());
        }
    }
}
