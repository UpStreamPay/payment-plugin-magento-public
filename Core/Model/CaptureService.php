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
 * Class CaptureService
 *
 * @package UpStreamPay\Core\Model
 */
class CaptureService
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
     * Capture order.
     *
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return InfoInterface
     * @throws LocalizedException
     */
    public function execute(InfoInterface $payment, float $amount): InfoInterface
    {
        $captureNotInSuccessFound = false;
        $upStreamPaySessionId = '';
        $amountCaptured = 0.00;

        //Get the authorized transactions with a success status for the current order.
        $this->searchCriteriaBuilder->addFilter(
            OrderTransactionsInterface::TRANSACTION_TYPE,
            OrderTransactions::CAPTURE_ACTION
        )->addFilter(
            OrderTransactionsInterface::STATUS,
            OrderTransactions::SUCCESS_STATUS
        )->addFilter(
            OrderTransactionsInterface::ORDER_ID,
            $payment->getOrder()->getEntityId()
        );

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $captureTransactions = $this->orderTransactionsRepository->getList($searchCriteria);

        foreach ($captureTransactions->getItems() as $captureTransaction) {
            if ($upStreamPaySessionId === '') {
                $upStreamPaySessionId = $captureTransaction->getSessionId();
            }

            if ($captureTransaction->getStatus() !== OrderTransactions::SUCCESS_STATUS) {
                //Handle errors better here. Not the scope of this US.
                $captureNotInSuccessFound = true;
            } elseif ($captureTransaction->getStatus() === OrderTransactions::SUCCESS_STATUS) {
                $amountCaptured += $captureTransaction->getAmount();
            }
        }

        //Every transaction has a capture success & the amount to capture matches the amount captured.
        if ($captureNotInSuccessFound === false && $amountCaptured === $amount) {
            //Every capture is a success, so the payment is captured.
            $payment
                ->setTransactionId($upStreamPaySessionId)
                ->setIsTransactionClosed(false)
                ->setIsTransactionPending(false)
                ->setIsTransactionApproved(true)
                ->setCurrencyCode($payment->getOrder()->getOrderCurrencyCode())
            ;
        } else {
            //Handle errors better here, not the scope of this US.
        }

        return $payment;
    }
}
