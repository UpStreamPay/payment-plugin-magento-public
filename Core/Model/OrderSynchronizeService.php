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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use NoTransactionsException;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Api\Data\OrderPaymentInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;

/**
 * Class OrderSynchronizeService
 *
 * @package UpStreamPay\Core\Model
 */
class OrderSynchronizeService
{
    /**
     * @param ClientInterface $client
     * @param OrderPaymentFactory $orderPaymentFactory
     * @param Session $checkoutSession
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param OrderTransactions $orderTransactions
     * @param OrderTransactionsRepositoryInterface $orderTransactionsRepository
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly OrderPaymentFactory $orderPaymentFactory,
        private readonly Session $checkoutSession,
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository,
        private readonly OrderTransactions $orderTransactions,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository
    ) {
    }

    /**
     * Synchronize the UpStream Pay order with Magento custom payment & transaction table.
     *
     * @return void
     * @throws LocalizedException
     * @throws NoTransactionsException
     */
    public function synchronizeAfterPlaceOrder(): void
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $quoteId = (int) $order->getQuoteId();
        $orderId = (int) $order->getEntityId();

        if (!$order || !$order->getId()) {
            throw new NoSuchEntityException(__('No order found in session'));
        }

        $orderTransactionsResponse = $this->client->getAllTransactionsForOrder($quoteId);

        if (count($orderTransactionsResponse) === 0) {
            throw new NoTransactionsException('No transactions found after place order with ID ' . $order->getId());
        }

        foreach ($orderTransactionsResponse as $orderTransactionResponse) {
            //Create a row in payment table for each original transaction, it means transactions without a parent_id.
            if (!isset($orderTransactionResponse['parent_transaction_id'])) {
                //TODO use model to create object & save it.
                //TODO check we dont have to data in DB before save.
                /** @var OrderPaymentInterface $orderPayment */
                $orderPayment = $this->orderPaymentFactory->create();

                $orderPayment
                    ->setSessionId($orderTransactionResponse['session_id'])
                    ->setDefaultTransactionId($orderTransactionResponse['id'])
                    ->setMethod($orderTransactionResponse['partner'] . ' / ' . $orderTransactionResponse['method'])
                    ->setType('primary')
                    ->setQuoteId($quoteId)
                    ->setOrderId($orderId)
                    ->setPaymentId((int) $order->getPayment()->getEntityId())
                    ->setAmount($orderTransactionResponse['plugin_result']['amount'])
                    ->setAmountCaptured(0.00)
                    ->setAmountRefunded(0.00)
                ;

                $this->orderPaymentRepository->save($orderPayment);
            }

            //TODO check we dont have to data in DB before save.
            $this->orderTransactions->createTransactionFromResponse($orderTransactionResponse, $orderId, $quoteId);
        }

        //Check DB to see if row in custom payment table.
            //Update if needed based on API response.
            //Create if no row.
        //Call proper action?
    }
}
