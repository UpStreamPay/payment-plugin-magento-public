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
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Session\PurseSessionDataManager;

/**
 * Class ManagePurseDataOnOrderObserver
 *
 * @package UpStreamPay\Core\Observer
 */
class ManagePurseDataOnOrderObserver implements ObserverInterface
{
    /**
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     */
    public function __construct(
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository
    ) {
    }

    /**
     * Set the purse session ID on order payment if the order was paid using upstream_pay payment method.
     *
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var Quote $quote */
        $quote = $observer->getData('quote');
        /** @var Order $order */
        $order = $observer->getData('order');

        if (null !== $order && null !== $quote) {
            $payment = $order->getPayment();

            if ($payment->getMethod() === Config::METHOD_CODE_UPSTREAM_PAY) {
                $payment->setData(
                    PurseSessionDataManager::PAYMENT_PURSE_SESSION_ID,
                    $quote->getPayment()->getData(PurseSessionDataManager::PAYMENT_PURSE_SESSION_ID)
                );

                $this->orderPaymentRepository->save($payment);
            }
        }
    }
}
