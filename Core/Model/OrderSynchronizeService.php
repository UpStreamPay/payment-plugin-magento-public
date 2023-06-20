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

use Magento\Checkout\Model\Session;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Api\Data\UpStreamPayOrderPaymentInterface;

/**
 * Class OrderSynchronizeService
 *
 * @package UpStreamPay\Core\Model
 */
class OrderSynchronizeService
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly UpStreamPayOrderPaymentFactory $orderPaymentFactory,
        private readonly Session $checkoutSession
    ) {
    }

    /**
     * Synchronize the UpStream Pay order with Magento custom payment & transaction table.
     *
     * @param int $quoteId
     *
     * @return void
     */
    public function synchronizeAfterPlaceOrder(int $quoteId): void
    {
        $orderTransactions = $this->client->getAllTransactionsForOrder($quoteId);
        $order = $this->checkoutSession->getLastRealOrder();

        if (!$order || !$order->getId()) {
            throw new \Exception('No order found in session');
        }

        foreach ($orderTransactions as $orderTransaction) {
            //Create a row in payment table for each original transaction, it means transactions without a parent_id.
            if (!isset($orderTransaction['parent_transaction_id'])) {
                /** @var UpStreamPayOrderPaymentInterface $upstreampayOrderPayment */
                $upstreampayOrderPayment = $this->orderPaymentFactory->create();

                $upstreampayOrderPayment
                    ->setSessionId($orderTransaction['session_id'])
                    ->setMethod($orderTransaction['partner'] . ' / ' . $orderTransaction['method'])
                    ->setType('primary')
                    ->setQuoteId($quoteId)
                    ->setOrderId($order->getId())
                    ->setPaymentId($order->getPayment()->getEntityId())
                    ->setAmount($orderTransaction['plugin_result']['amount'])
                    ->setAmountCaptured(0.00)
                    ->setAmountRefunded(0.00)
                ;


            }
        }

        //Create transaction based on response.

        //Check DB to see if row in custom payment table.
            //Update if needed based on API response.
            //Create if no row.
        //Call proper action?
    }
}
