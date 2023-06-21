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

namespace UpStreamPay\Core\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use UpStreamPay\Client\Model\Client\ClientInterface;

/**
 * Class CaptureService
 *
 * @package UpStreamPay\Core\Model
 */
class CaptureService
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly OrderTransactions $orderTransactions,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly InvoiceSender $invoiceSender
    ) {
    }

    public function capture(InfoInterface $payment, float $amount)
    {
        //TODO Define what to do here.
    }

    /**
     * Capture order after place order.
     * @TODO WIP, this only handles single payment with authorize success & no error.
     *
     * @param Order $order
     *
     * @return void
     * @throws LocalizedException
     */
    public function captureAfterPlaceOrder(Order $order): void
    {
        $authorizedAmount = 0;
        $capturedAmount = 0;
        $authorizeTransactionId = '';

        $orderTransactionsResponse = $this->client->getAllTransactionsForOrder((int) $order->getQuoteId());

        foreach ($orderTransactionsResponse as $orderTransactionResponse) {
            if ($orderTransactionResponse['status']['action'] === OrderTransactions::AUTHORIZE_ACTION) {
                if ($orderTransactionResponse['status']['state'] === OrderTransactions::SUCCESS_STATUS) {
                    $authorizedAmount += $orderTransactionResponse['plugin_result']['amount'];
                    $authorizeTransactionId = $orderTransactionResponse['id'];
                }
            } elseif ($orderTransactionResponse['status']['action'] === OrderTransactions::CAPTURE_ACTION) {
                if ($orderTransactionResponse['status']['state'] !== OrderTransactions::ERROR_STATUS) {
                    $capturedAmount += $orderTransactionResponse['plugin_result']['amount'];
                } else {
                    //Capture error found, handle it.
                }
            }
        }

        if ($capturedAmount !== 0) {
            //Capture is done & is waiting or success.
            //Handle this later.
            //Probably nothing to do except wait for notification if waiting.
            //If capture is success then process order to reflect the success of the capture.
            //Add comment to order?
        } elseif ($capturedAmount === 0) {
            //No capture has been found, capture now.
            $requestBody = [
                'order' => [
                    'amount' => $order->getGrandTotal(),
                    'currency_code' => $order->getOrderCurrencyCode()
                ],
                'amount' => $order->getGrandTotal()
            ];
            $captureResponse = $this->client->capture($authorizeTransactionId, $requestBody);

            //Save the response in DB to keep track of the transactions.
            $captureTransaction = $this->orderTransactions->createTransactionFromResponse(
                $captureResponse,
                (int) $order->getEntityId(),
                (int) $order->getQuoteId()
            );

            //If the API call was a success, we can process the order & tell Magento capture was ok.
            if ($captureTransaction->getStatus() === OrderTransactions::SUCCESS_STATUS) {
                /** @var OrderPaymentInterface $payment */
                $payment = $order->getPayment();

                $payment->setTransactionId($captureTransaction->getTransactionId())
                    ->setParentTransactionId($captureTransaction->getParentTransactionId())
                    ->setCurrencyCode($order->getOrderCurrencyCode())
                    ->setIsTransactionClosed(0);

                if ($order->getState() === Order::STATE_PAYMENT_REVIEW) {
                    $order->setState(Order::STATE_PROCESSING);
                }

                $payment->registerCaptureNotification($captureTransaction->getAmount());

                $this->orderRepository->save($order);

                /** @var Invoice $invoice */
                $invoice = $payment->getCreatedInvoice();

                //Notify customer
                if ($invoice && !$invoice->getEmailSent()) {
                    $this->invoiceSender->send($invoice);
                    $order->addCommentToStatusHistory(
                        __('You notified customer about invoice #%1.', $invoice->getIncrementId())
                    )->setIsCustomerNotified(true);

                    $this->orderRepository->save($order);
                }
            }
        }
    }
}
