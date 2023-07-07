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
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Processor;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Exception\AuthorizeErrorException;
use UpStreamPay\Core\Exception\CaptureErrorException;
use UpStreamPay\Core\Exception\OrderErrorException;

/**
 * Class NotificationService
 *
 * @package UpStreamPay\Core\Model
 */
class NotificationService
{
    /**
     * @param Config $config
     * @param Processor $paymentProcessor
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderTransactionsRepositoryInterface $orderTransactionsRepository
     * @param LoggerInterface $logger
     * @param InvoiceRepositoryInterface $invoiceRepository
     */
    public function __construct(
        private readonly Config $config,
        private readonly Processor $paymentProcessor,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository,
        private readonly LoggerInterface $logger,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
    ) {
    }

    /**
     * @param array $notification
     *
     * @return void
     * @throws LocalizedException
     */
    public function execute(array $notification)
    {
        $transaction = $this->orderTransactionsRepository->getByTransactionId($notification['id']);

        if ($this->config->getIsDebugEnabled()) {
            $this->logger->debug(
                sprintf(
                    'Receiving notification for transaction regarding order %s:',
                    $transaction->getOrderId(),
                )
            );
            $this->logger->debug(print_r($notification, true));
        }

        //Only deal with known transactions in case of a real status update.
        if ($transaction && $transaction->getEntityId() && $transaction->getStatus() !== $notification['status']['state']) {
            switch ($notification['status']['action']) {
                case OrderTransactions::AUTHORIZE_ACTION:
                    $order = $this->orderRepository->get($transaction->getOrderId());
                    $payment = $order->getPayment();

                    $transaction->setStatus($notification['status']['state']);
                    $this->orderTransactionsRepository->save($transaction);

                    try {
                        if ($this->config->getPaymentAction() === MethodInterface::ACTION_AUTHORIZE) {
                            $this->paymentProcessor->authorize($payment, true, $order->getTotalDue());
                        } elseif ($this->config->getPaymentAction() === MethodInterface::ACTION_ORDER) {
                            $this->paymentProcessor->order($payment, $order->getTotalDue());
                        }
                    } catch (AuthorizeErrorException | OrderErrorException $authorizeErrorException) {
                        //This is thrown by the authorize / order function in UpStream Pay payment method.
                        $payment->deny();
                    }

                    $this->orderRepository->save($order);

                    break;
                case OrderTransactions::CAPTURE_ACTION:
                    $transaction->setStatus($notification['status']['state']);
                    $this->orderTransactionsRepository->save($transaction);

                    if ($transaction->getInvoiceId() !== null) {
                        //Get all the object we need & set invoice on payment.
                        $invoice = $this->invoiceRepository->get($transaction->getInvoiceId());
                        //Very important to retrieve the order from the invoice because this is the object Magento
                        //Will use when paying the invoice.
                        $order = $invoice->getOrder();
                        $payment = $order->getPayment();
                        $payment->setCreatedInvoice($invoice);
                    } else {
                        //If invoice id is null on transaction then we probably are in an action order context.
                        $order = $this->orderRepository->get($transaction->getOrderId());
                        $payment = $order->getPayment();
                    }

                    try {
                        if ($transaction->getInvoiceId() !== null) {
                            $this->paymentProcessor->capture($payment, $invoice);

                            //After capture is done trigger pay of the invoice.
                            if ($invoice->getIsPaid()) {
                                $invoice->pay();
                            }

                            $this->orderRepository->save($order);
                            $this->invoiceRepository->save($invoice);
                        } elseif ($this->config->getPaymentAction() === MethodInterface::ACTION_ORDER
                            && $transaction->getInvoiceId() === null
                            && $order->getStatus() === Order::STATE_PAYMENT_REVIEW) {
                            //Make sure that we trigger this only when we are in order action, with no invoice &
                            //an order that is in payment review.
                            $this->paymentProcessor->order($payment, $order->getTotalDue());
                            $this->orderRepository->save($order);
                        }
                    } catch (CaptureErrorException | OrderErrorException $captureErrorException) {
                        //This is thrown by the capture / order function in UpStream Pay payment method.
                        $order->addCommentToStatusHistory(
                            'Notification has error on capture transaction, denying the payment'
                        );
                        $payment->deny();
                        //Very important to save the order coming from the payment because several things will be
                        //set on this object.
                        $this->orderRepository->save($payment->getOrder());
                    }

                    break;
                case OrderTransactions::REFUND_ACTION:
                    //In case of a refund notification we only need to update the transaction
                    //And add a comment on the order, that's it.
                    $transaction->setStatus($notification['status']['state']);
                    $this->orderTransactionsRepository->save($transaction);

                    $order = $this->orderRepository->get($transaction->getOrderId());

                    $order->addCommentToStatusHistory(sprintf(
                        'Transaction %s %s for %s with amount %s in status %s',
                        $transaction->getTransactionType(),
                        $transaction->getTransactionId(),
                        $transaction->getMethod(),
                        $transaction->getAmount(),
                        $transaction->getStatus()
                    ));

                    //In case of an error a manual refund must be done.
                    if ($transaction->getStatus() === OrderTransactions::ERROR_STATUS) {
                        $errorMessage = sprintf(
                            'Transaction refund with ID %s is in error, refund it in UpStream admin panel.',
                            $transaction->getTransactionId()
                        );
                        $this->logger->critical($errorMessage);
                        $order->addCommentToStatusHistory($errorMessage);
                    }

                    $this->orderRepository->save($order);

                    break;
            }
        }
    }
}
