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
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;

/**
 * Class AuthorizeService
 *
 * @package UpStreamPay\Core\Model
 */
class AuthorizeService
{
    /**
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderTransactionsRepositoryInterface $orderTransactionsRepository
     */
    public function __construct(
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository
    ) {
    }

    /**
     * Check if we can authorize the given amount.
     * This class doesn't call any API, it only checks & set the payment transaction state.
     *
     * If everything is ok => setIsTransactionApproved to true.
     *
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return void
     * @throws LocalizedException
     */
    public function execute(InfoInterface $payment, float $amount): void
    {
        $authorizeNotInSuccessFound = false;
        $upStreamPaySessionId = '';
        $amountAuthorized = 0.00;

        //Get the authorized transactions with a success status for the current order.
        $this->searchCriteriaBuilder->addFilter(
            OrderTransactionsInterface::TRANSACTION_TYPE,
            OrderTransactions::AUTHORIZE_ACTION
        )->addFilter(
            OrderTransactionsInterface::STATUS,
            OrderTransactions::SUCCESS_STATUS
        )->addFilter(
            OrderTransactionsInterface::ORDER_ID,
            $payment->getOrder()->getEntityId()
        );

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $authorizeTransactions = $this->orderTransactionsRepository->getList($searchCriteria);

        foreach ($authorizeTransactions->getItems() as $authorizeTransaction) {
            if ($upStreamPaySessionId === '') {
                $upStreamPaySessionId = $authorizeTransaction->getSessionId();
            }

            if ($authorizeTransaction->getStatus() !== OrderTransactions::SUCCESS_STATUS) {
                //Handle errors better here. Not the scope of this US.
                $authorizeNotInSuccessFound = true;
            } elseif ($authorizeTransaction->getStatus() === OrderTransactions::SUCCESS_STATUS) {
                $amountAuthorized += $authorizeTransaction->getAmount();
            }
        }

        //Every transaction has an authorize success & the amount to authorize matches the amount authorized.
        if ($authorizeNotInSuccessFound === false && $amountAuthorized === $amount) {
            //Every authorize is a success, so the payment is authorized.
            $payment
                ->setTransactionId($upStreamPaySessionId)
                ->setIsTransactionClosed(false)
                ->setIsTransactionApproved(true)
                ->setCurrencyCode($payment->getOrder()->getOrderCurrencyCode())
            ;
        } else {
            //Handle errors better here, not the scope of this US.
        }
    }
}
