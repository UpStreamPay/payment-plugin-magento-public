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
use Magento\Sales\Model\Order\Payment\Processor;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Exception\AuthorizeErrorException;
use UpStreamPay\Core\Exception\CaptureErrorException;

/**
 * Class NotificationService
 *
 * @package UpStreamPay\Core\Model
 */
class NotificationService
{
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
                    'Receiving notification for transaction regarding order %s & invoice %s:',
                    $transaction->getOrderId(),
                    $transaction->getInvoiceId()
                )
            );
            $this->logger->debug(print_r($notification, true));
        }

        //Only deal with known transactions in case of a real status update.
        if ($transaction && $transaction->getEntityId() && $transaction->getStatus() !== $notification['status']['state']) {
            //@TODO Add more case as we implement more things.
            switch ($notification['status']['action']) {
                case OrderTransactions::AUTHORIZE_ACTION:
                    $order = $this->orderRepository->get($transaction->getOrderId());
                    $payment = $order->getPayment();

                    if ($this->config->getPaymentAction() === MethodInterface::ACTION_AUTHORIZE) {
                        $transaction->setStatus($notification['status']['state']);
                        $this->orderTransactionsRepository->save($transaction);

                        try {
                            $this->paymentProcessor->authorize($payment, true, $order->getTotalDue());
                        } catch (AuthorizeErrorException $authorizeErrorException) {
                            //This is thrown by the authorize function in UpStream Pay payment method.
                            $payment->deny();
                        }

                        $this->orderRepository->save($order);
                    }

                    break;
                case OrderTransactions::CAPTURE_ACTION:
                    if ($this->config->getPaymentAction() === MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
                        $transaction->setStatus($notification['status']['state']);
                        $this->orderTransactionsRepository->save($transaction);

                        //Get all the object we need & set invoice on payment.
                        $invoice = $this->invoiceRepository->get($transaction->getInvoiceId());
                        //Very important to retrieve the order from the invoice because this is the object Magento
                        //Will use when paying the invoice.
                        $order = $invoice->getOrder();
                        $payment = $order->getPayment();
                        $payment->setCreatedInvoice($invoice);

                        try {
                            $this->paymentProcessor->capture($payment, $invoice);

                            //After capture is done trigger pay of the invoice.
                            if ($invoice->getIsPaid()) {
                                $invoice->pay();
                            }

                            $this->orderRepository->save($order);
                            $this->invoiceRepository->save($invoice);
                        } catch (CaptureErrorException $captureErrorException) {
                            //This is thrown by the capture function in UpStream Pay payment method.
                            $order->addCommentToStatusHistory(
                                'Notification has error on capture transaction, denying the payment'
                            );
                            $payment->deny();
                            //Very important to save the order coming from the payment because several things will be
                            //set on this object.
                            $this->orderRepository->save($payment->getOrder());
                        }
                    }
            }
        }
    }
}
