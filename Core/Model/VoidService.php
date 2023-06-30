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
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\Data\OrderInterface;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Api\Data\OrderPaymentInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
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
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param Config $config
     */
    public function __construct(
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository,
        private readonly ClientInterface $client,
        private readonly OrderTransactions $orderTransactions,
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository,
        private readonly Config $config
    ) {
    }

    /**
     * Void authorize success transaction for a given order.
     *
     * @param InfoInterface $payment
     *
     * @return InfoInterface
     * @throws LocalizedException
     */
    public function execute(InfoInterface $payment): InfoInterface
    {
        /** @var OrderInterface $order */
        $order = $payment->getOrder();

        if ($this->config->getPaymentAction() === MethodInterface::ACTION_AUTHORIZE) {
            $this->voidAllAuthorizeTransactions($order);
        } elseif ($this->config->getPaymentAction() === MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
            $this->voidAllCaptureTransactions($order);
        }

        return $payment;
    }

    /**
     * Void all authorize transactions.
     *
     * @param OrderInterface $order
     *
     * @return void
     * @throws LocalizedException
     */
    private function voidAllAuthorizeTransactions(OrderInterface $order): void
    {
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
                    'currency_code' => $order->getOrderCurrencyCode(),
                ],
                'amount' => $authorizeTransaction->getAmount(),
            ];

            //Void from API.
            $voidResponse = $this->client->void($authorizeTransaction->getTransactionId(), $body);

            //Save the void transaction in DB.
            $this->orderTransactions->createTransactionFromResponse(
                $voidResponse,
                (int) $order->getEntityId(),
                (int) $order->getQuoteId(),
                (int) $authorizeTransaction->getParentPaymentId()
            );
        }
    }

    /**
     * Void all Capture transactions.
     *
     * A capture transaction has to be refunded on the API.
     * In this scenario this takes place if an error is detected right after the place order.
     *
     * @param OrderInterface $order
     *
     * @return void
     * @throws LocalizedException
     */
    private function voidAllCaptureTransactions(OrderInterface $order): void
    {
        $refunds = [];

        //Get the captured transactions with a success status for the current order.
        $this->searchCriteriaBuilder->addFilter(
            OrderTransactionsInterface::TRANSACTION_TYPE,
            OrderTransactions::CAPTURE_ACTION
        )->addFilter(
            OrderTransactionsInterface::ORDER_ID,
            $order->getEntityId()
        )->addFilter(
            OrderTransactionsInterface::STATUS,
            OrderTransactions::SUCCESS_STATUS
        );

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $captureTransactions = $this->orderTransactionsRepository->getList($searchCriteria);

        foreach ($captureTransactions->getItems() as $captureTransaction) {
            $body = [
                'order' => [
                    'amount' => $order->getGrandTotal(),
                    'currency_code' => $order->getOrderCurrencyCode(),
                ],
                'amount' => $captureTransaction->getAmount(),
            ];

            //Refund from API.
            $refundResponse = $this->client->refund($captureTransaction->getTransactionId(), $body);

            //Save the void transaction in DB.
            $refundTransaction = $this->orderTransactions->createTransactionFromResponse(
                $refundResponse,
                (int) $order->getEntityId(),
                (int) $order->getQuoteId(),
                (int) $captureTransaction->getParentPaymentId()
            );

            $refunds[$refundTransaction->getParentPaymentId()][] = $refundTransaction;
        }

        $this->searchCriteriaBuilder->addFilter(
            OrderPaymentInterface::ENTITY_ID,
            array_keys($refunds),
            'in'
        );

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $orderPayments = $this->orderPaymentRepository->getList($searchCriteria);

        //Set on each payment the total refunded.
        foreach ($orderPayments->getItems() as $orderPayment) {
            $totalRefundForPayment = 0.00;
            /** @var OrderTransactionsInterface $refund */
            foreach ($refunds[$orderPayment->getEntityId()] as $refund) {
                $totalRefundForPayment += $refund->getAmount();
            }

            $orderPayment->setAmountRefunded($totalRefundForPayment);
            $this->orderPaymentRepository->save($orderPayment);
        }
    }
}
