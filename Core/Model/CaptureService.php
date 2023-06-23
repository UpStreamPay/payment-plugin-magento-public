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

use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use UpStreamPay\Client\Exception\NoOrderFoundException;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;

/**
 * Class CaptureService
 *
 * @package UpStreamPay\Core\Model
 */
class CaptureService
{
    /**
     * @param ClientInterface $client
     * @param OrderTransactions $orderTransactions
     * @param OrderRepositoryInterface $orderRepository
     * @param InvoiceSender $invoiceSender
     * @param OrderTransactionsRepositoryInterface $orderTransactionsRepository
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly OrderTransactions $orderTransactions,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly InvoiceSender $invoiceSender,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository
    ) {
    }

    /**
     * Capture order.
     * @TODO WIP, this only handles single payment with authorize success & no error.
     * @TODO WIP, this only captures the whole order for now, we should pass an amount parameter to handle capture
     * @TODO of a specific amount.
     *
     * @param OrderInterface $order
     *
     * @return void
     * @throws LocalizedException
     * @throws GuzzleException
     * @throws \JsonException
     * @throws NoOrderFoundException
     * @throws \Exception
     */
    public function capture(OrderInterface $order): void
    {
        $authorizedAmount = 0;
        $capturedAmount = 0;
        $authorizeTransactionId = '';
        $waitingCapture = false;
        $errorCapture = false;
        $captureTransactionId = '';

        //@TODO should be removed in the future, we should synchronize the data first in our DB and use that
        //@TODO for the capture process instead of calling the API again here.
        $orderTransactionsResponse = $this->client->getAllTransactionsForOrder((int) $order->getQuoteId());

        foreach ($orderTransactionsResponse as $orderTransactionResponse) {
            //For now, we don't check the authorize state, it's supposed to be success.
            if ($orderTransactionResponse['status']['action'] === OrderTransactions::AUTHORIZE_ACTION) {
                if ($orderTransactionResponse['status']['state'] === OrderTransactions::SUCCESS_STATUS) {
                    $authorizedAmount += $orderTransactionResponse['plugin_result']['amount'];
                    $authorizeTransactionId = $orderTransactionResponse['id'];
                }
            } elseif ($orderTransactionResponse['status']['action'] === OrderTransactions::CAPTURE_ACTION) {
                if ($orderTransactionResponse['status']['state'] === OrderTransactions::WAITING_STATUS) {
                    $waitingCapture = true;
                } elseif ($orderTransactionResponse['status']['state'] === OrderTransactions::ERROR_STATUS) {
                    //Capture error found, handle it.
                    $errorCapture = true;
                } elseif ($orderTransactionResponse['status']['state'] === OrderTransactions::SUCCESS_STATUS) {
                    //Nothing to do here now but maybe in the future check if captured amount matches the amount to
                    //capture.
                    $captureTransactionId = $orderTransactionResponse['id'];
                }
            }
        }

        //@TODO Update the conditions with something more robust.
        //@TODO For now we only handle a simple success case.
        if ($waitingCapture === false && $errorCapture === false && $captureTransactionId !== '') {
            //Capture is done already, process order.
            $captureTransaction = $this->orderTransactionsRepository->getByTransactionId($captureTransactionId);
            $this->processOrderAfterSuccessfulCapture($order, $captureTransaction);
        } elseif ($waitingCapture === false && $errorCapture === false && $captureTransactionId === '') {
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
                $this->processOrderAfterSuccessfulCapture($order, $captureTransaction);
            }
        }
    }

    /**
     * Process the order in case of successful capture.
     *
     * @param OrderInterface $order
     * @param OrderTransactionsInterface $captureTransaction
     *
     * @return void
     * @throws \Exception
     */
    private function processOrderAfterSuccessfulCapture(
        OrderInterface $order,
        OrderTransactionsInterface $captureTransaction
    ): void {
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
