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
use UpStreamPay\Core\Exception\CaptureErrorException;

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
     * @throws CaptureErrorException
     */
    public function execute(InfoInterface $payment, float $amount): InfoInterface
    {
        $atLeastOneCaptureError = false;
        $atLeastOneCaptureWaiting = false;
        $upStreamPaySessionId = '';
        $amountCaptured = 0.00;

        //Get the capture transactions with a success status for the current order.
        $this->searchCriteriaBuilder->addFilter(
            OrderTransactionsInterface::TRANSACTION_TYPE,
            OrderTransactions::CAPTURE_ACTION
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

            if ($captureTransaction->getStatus() === OrderTransactions::ERROR_STATUS) {
                $atLeastOneCaptureError = true;
            } elseif ($captureTransaction->getStatus() === OrderTransactions::SUCCESS_STATUS) {
                $amountCaptured += $captureTransaction->getAmount();
            } elseif ($captureTransaction->getStatus() === OrderTransactions::WAITING_STATUS) {
                $atLeastOneCaptureWaiting = true;
            }
        }

        //Every transaction has a capture success & the amount to capture matches the amount captured.
        if (!$atLeastOneCaptureError && $amountCaptured === $amount) {
            //Every capture is a success, so the payment is captured.
            $payment
                ->setTransactionId($upStreamPaySessionId)
                ->setIsTransactionClosed(false)
                ->setIsTransactionPending(false)
                ->setIsTransactionApproved(true)
                ->setCurrencyCode($payment->getOrder()->getOrderCurrencyCode())
            ;
        } elseif ($atLeastOneCaptureError && !$atLeastOneCaptureWaiting) {
            //There is at least one capture in error and no capture in waiting so we can safely perform a refund on all
            //capture for this order.
            throw new CaptureErrorException(
                'At least one Capture transaction is in error, refund all Capture in success.'
            );
        }

        return $payment;
    }
}
