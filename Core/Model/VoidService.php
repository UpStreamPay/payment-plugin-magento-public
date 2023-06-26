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

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;

/**
 * Class VoidService
 *
 * @package UpStreamPay\Core\Model
 */
class VoidService
{
    /**
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderTransactionsRepositoryInterface $orderTransactionsRepository
     * @param ClientInterface $client
     * @param OrderTransactions $orderTransactions
     */
    public function __construct(
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository,
        private readonly ClientInterface $client,
        private readonly OrderTransactions $orderTransactions
    ) {
    }

    /**
     * Void authorize success transaction for a given order.
     *
     * @param InfoInterface $payment
     *
     * @return void
     * @throws LocalizedException
     */
    public function execute(InfoInterface $payment): void
    {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        //Get the authorized transactions with a success status for the current order.
        $this->searchCriteriaBuilder->addFilter(
            OrderTransactionsInterface::TRANSACTION_TYPE,
            OrderTransactions::AUTHORIZE_ACTION
        )->addFilter(
            OrderTransactionsInterface::ORDER_ID,
            $order->getEntityId()
        )->addFilter(
            OrderTransactionsInterface::STATUS,
            OrderTransactions::SUCCESS_STATUS
        );

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $authorizeTransactions = $this->orderTransactionsRepository->getList($searchCriteria);

        foreach ($authorizeTransactions->getItems() as $authorizeTransaction) {
            $body = [
                'order' => [
                    'amount' => $order->getGrandTotal(),
                    'currency_code' => $order->getOrderCurrencyCode()
                ],
                'amount' => $authorizeTransaction->getAmount()
            ];

            //Void from API.
            $voidResponse = $this->client->void($authorizeTransaction->getTransactionId(), $body);

            //Save the void transaction in DB.
            $this->orderTransactions->createTransactionFromResponse(
                $voidResponse,
                (int) $order->getEntityId(),
                (int) $order->getQuoteId()
            );
        }
    }
}
