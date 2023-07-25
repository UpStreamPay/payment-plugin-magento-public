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

namespace UpStreamPay\Core\Model\PaymentFinder;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use UpStreamPay\Core\Api\Data\OrderPaymentInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Model\PaymentMethod;

/**
 * Class AllTransactionsFinder
 *
 * @package UpStreamPay\Core\Model\PaymentFinder
 */
class AllTransactionsFinder
{
    /**
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param OrderTransactionsRepositoryInterface $orderTransactionsRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
     * Find all transactions based on the transaction type, status & order id.
     *
     * This returns transactions by respecting the order secondary first, then primary.
     * It returns all transactions for the order.
     *
     * @param string $transactionType
     * @param int $orderId
     * @param string $status
     * @param null|bool|int $invoiceId
     *
     * @return OrderTransactionsInterface[]
     * @throws LocalizedException
     */
    public function execute(
        string $transactionType,
        int $orderId,
        string $status,
        null|bool|int $invoiceId = false,
    ): array
    {
        $secondaryPaymentsEntityId = [];
        $primaryPaymentsEntityId = [];

        //Get all order payments made for the given order.
        $this->searchCriteriaBuilder->addFilter(
            OrderPaymentInterface::ORDER_ID,
            $orderId
        );

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $orderPayments = $this->orderPaymentRepository->getList($searchCriteria);

        //Get all payment methods that are secondary or primary.
        foreach ($orderPayments->getItems() as $orderPayment) {
            if ($orderPayment->getType() === PaymentMethod::SECONDARY) {
                $secondaryPaymentsEntityId[$orderPayment->getEntityId()][] = $orderPayment;
            } else {
                $primaryPaymentsEntityId[$orderPayment->getEntityId()][] = $orderPayment;
            }
        }

        //Get all secondary transactions.
        $this->filterTransactions($secondaryPaymentsEntityId, $transactionType, $orderId, $status, $invoiceId);

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $secondaryTransactions = $this->orderTransactionsRepository->getList($searchCriteria)->getItems();

        $this->filterTransactions($primaryPaymentsEntityId, $transactionType, $orderId, $status, $invoiceId);

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $primaryTransactions = $this->orderTransactionsRepository->getList($searchCriteria)->getItems();

        //Put the secondary transactions first.
        return array_merge($secondaryTransactions, $primaryTransactions);
    }

    /**
     * Filter the transactions.
     *
     * @param array $paymentEntityId
     * @param string $transactionType
     * @param int $orderId
     * @param string $status
     * @param null|bool|int $invoiceId
     *
     * @return void
     */
    private function filterTransactions(
        array $paymentEntityId,
        string $transactionType,
        int $orderId,
        string $status,
        null|bool|int $invoiceId = false
    ): void {
        $this->searchCriteriaBuilder->addFilter(
            OrderTransactionsInterface::PARENT_PAYMENT_ID,
            array_keys($paymentEntityId),
            'in'
        )->addFilter(
            OrderTransactionsInterface::TRANSACTION_TYPE,
            $transactionType
        )->addFilter(
            OrderTransactionsInterface::ORDER_ID,
            $orderId
        )->addFilter(
            OrderTransactionsInterface::STATUS,
            $status
        );

        //Optionnals filters.
        if ($invoiceId !== false && $invoiceId === null) {
            $this->searchCriteriaBuilder->addFilter(
                OrderTransactionsInterface::INVOICE_ID,
                $invoiceId,
                'null'
            );
        } elseif ($invoiceId !== false) {
            $this->searchCriteriaBuilder->addFilter(
                OrderTransactionsInterface::INVOICE_ID,
                $invoiceId
            );
        }
    }
}
