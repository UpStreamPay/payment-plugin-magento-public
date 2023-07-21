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
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Model\Config;

/**
 * Class responsive for sending order emails when it's created through storefront.
 */
class SubmitObserverCustom implements ObserverInterface
{
    /**
     * @param LoggerInterface $logger
     * @param OrderSender $orderSender
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly OrderSender $orderSender
    ) {
    }

    /**
     * Send order email.
     *
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer)
    {
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
                if ($order->getPayment()->getMethod() !== Config::METHOD_CODE_UPSTREAM_PAY) {
                    $this->orderSender->send($order);
                }
            } catch (\Throwable $e) {
                $this->logger->critical($e);
            }
        }
    }
}
