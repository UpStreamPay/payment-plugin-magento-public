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

namespace UpStreamPay\Core\Model\PaymentFinder;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use UpStreamPay\Core\Api\Data\OrderPaymentInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Model\PaymentMethod;

/**
 * Class FindAllTransactions
 *
 * @package UpStreamPay\Core\Model\PaymentFinder
 */
class FindAllTransactions
{
    /**
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param OrderTransactionsRepositoryInterface $orderTransactionsRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,) {
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
     *
     * @return array
     * @throws LocalizedException
     */
    public function execute(string $transactionType, int $orderId, string $status): array
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

        foreach ($orderPayments->getItems() as $orderPayment) {
            if ($orderPayment->getType() === PaymentMethod::SECONDARY) {
                $secondaryPaymentsEntityId[$orderPayment->getEntityId()][] = $orderPayment;
            } else {
                $primaryPaymentsEntityId[$orderPayment->getEntityId()][] = $orderPayment;
            }
        }

        $this->searchCriteriaBuilder->addFilter(
            OrderTransactionsInterface::PARENT_PAYMENT_ID,
            array_keys($secondaryPaymentsEntityId),
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

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $secondaryTransactions = $this->orderTransactionsRepository->getList($searchCriteria)->getItems();

        $this->searchCriteriaBuilder->addFilter(
            OrderTransactionsInterface::PARENT_PAYMENT_ID,
            array_keys($primaryPaymentsEntityId),
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

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $primaryTransactions = $this->orderTransactionsRepository->getList($searchCriteria)->getItems();

        //Put the secondary transactions first.
        return array_merge($secondaryTransactions, $primaryTransactions);
    }
}
