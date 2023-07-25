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

namespace UpStreamPay\Core\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Container\InvoiceIdentity;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Psr\Log\LoggerInterface;
use Throwable;
use UpStreamPay\Core\Model\Config;

/**
 * Class SendUpStreamPayInvoiceEmailObserver
 *
 * @package UpStreamPay\Core\Observer
 */
class SendUpStreamPayInvoiceEmailObserver implements ObserverInterface
{
    /**
     * @param LoggerInterface $logger
     * @param InvoiceSender $invoiceSender
     * @param InvoiceIdentity $invoiceIdentity
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly InvoiceSender $invoiceSender,
        private readonly InvoiceIdentity $invoiceIdentity
    ) {
    }

    /**
     * Send invoice email if allowed.
     *
     * @see sendEmailInvoiceUpstream
     *
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if (!$this->isInvoiceEmailAllowed()) {
            return;
        }

        /** @var  Quote $quote */
        $quote = $observer->getEvent()->getQuote();
        /** @var  Order $order */
        $order = $observer->getEvent()->getOrder();

        /**
         * a flag to set that there will be redirect to third party after confirmation
         */
        $redirectUrl = $quote->getPayment()->getOrderPlaceRedirectUrl();
        if (!$redirectUrl && $order->getCanSendNewEmailFlag()) {
            try {
                $invoice = current($order->getInvoiceCollection()->getItems());
                if ($invoice) {
                    //Add this condition to block emails only if using upstream pay.
                    if ($order->getPayment()->getMethod() !== Config::METHOD_CODE_UPSTREAM_PAY) {
                        $this->invoiceSender->send($invoice);
                    }
                }
            } catch (Throwable $exception) {
                $this->logger->critical($exception);
            }
        }
    }

    /**
     * Is invoice email sending enabled
     *
     * @return bool
     */
    private function isInvoiceEmailAllowed(): bool
    {
        return $this->invoiceIdentity->isEnabled();
    }
}
