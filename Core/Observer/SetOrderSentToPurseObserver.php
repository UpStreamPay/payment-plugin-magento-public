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
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use UpStreamPay\Core\Model\Config;

/**
 * Class SetOrderSentToPurseObserver
 * @see order_sent_to_purse_event
 *
 * @package UpStreamPay\Core\Observer
 */
class SetOrderSentToPurseObserver implements ObserverInterface
{
    public const EVENT_NAME = 'order_sent_to_purse_event';
    public const ORDER_SENT_TO_PURSE = 'order_sent_to_purse';

    /**
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository
    ) {
    }

    /**
     * Flag the order as sent to purse.
     *
     * @inheritDoc
     */
    public function execute(Observer $observer): void
    {
        /** @var Order $order */
        $order = $observer->getData('order');

        if ($order->getPayment()->getMethod() === Config::METHOD_CODE_UPSTREAM_PAY) {
            $order->setData(self::ORDER_SENT_TO_PURSE, 1);

            $this->orderRepository->save($order);
        }
    }
}
