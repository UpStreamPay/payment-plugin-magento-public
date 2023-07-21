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

namespace UpStreamPay\Core\Model\Actions;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\FloatComparator;
use Magento\Payment\Model\InfoInterface;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Exception\AuthorizeErrorException;
use UpStreamPay\Core\Exception\CaptureErrorException;
use UpStreamPay\Core\Exception\OrderErrorException;
use UpStreamPay\Core\Model\OrderTransactions;

/**
 * Class OrderService
 *
 * @package UpStreamPay\Core\Model\Actions
 */
class OrderService
{
    /**
     * @param AuthorizeService $authorizeService
     * @param CaptureService $captureService
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderTransactionsRepositoryInterface $orderTransactionsRepository
     * @param LoggerInterface $logger
     * @param FloatComparator $floatComparator
     */
    public function __construct(
        private readonly AuthorizeService $authorizeService,
        private readonly CaptureService $captureService,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository,
        private readonly LoggerInterface $logger,
        private readonly FloatComparator $floatComparator
    ) {
    }

    /**
     * For the order action we have to check first for the authorized transactions & then check the captured
     * transactions.
     * After the place order all we want is success for both.
     *
     * This function should only be called after place order or in case of waiting & webhook notification.
     *
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return InfoInterface
     * @throws LocalizedException
     * @throws AuthorizeErrorException
     * @throws CaptureErrorException
     * @throws OrderErrorException
     */
    public function execute(InfoInterface $payment, float $amount): InfoInterface
    {
        $amountToAuthorize = 0;
        $amountToCapture = 0;

        //Get the transactions for the current order.
        $this->searchCriteriaBuilder->addFilter(
            OrderTransactionsInterface::ORDER_ID,
            $payment->getOrder()->getEntityId()
        )->addFilter(
            OrderTransactions::TRANSACTION_TYPE,
            [
                OrderTransactions::CAPTURE_ACTION,
                OrderTransactions::AUTHORIZE_ACTION
            ],
            'in'
        )
        ;

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $transactions = $this->orderTransactionsRepository->getList($searchCriteria)->getItems();

        //If there are no transactions, no need to go further.
        if (count($transactions) === 0) {
            $payment->setIsTransactionPending(true);

            return $payment;
        }

        foreach ($transactions as $transaction) {
            if ($transaction->getTransactionType() === OrderTransactions::CAPTURE_ACTION) {
                $amountToCapture += $transaction->getAmount();
            } elseif ($transaction->getTransactionType() === OrderTransactions::AUTHORIZE_ACTION) {
                $amountToAuthorize += $transaction->getAmount();
            }
        }

        //Check that the amount captured and authorized match the total due.
        if (!$this->floatComparator->equal($amount, ($amountToAuthorize + $amountToCapture))) {
            $errorMessage = sprintf(
                'Amount to capture & authorize is different than total amount of order.
                Order total is %s and capture total is %s and authorize total is %s for order with ID %s.',
                $amount,
                $amountToCapture,
                $amountToAuthorize,
                $payment->getOrder()->getEntityId()
            );

            $payment->getOrder()->addCommentToStatusHistory($errorMessage);
            $this->logger->critical($errorMessage);

            throw new OrderErrorException($errorMessage);
        }

        $payment = $this->authorizeService->execute($payment, $amountToAuthorize);
        $isAuthorizeTransactionPending = $amountToAuthorize > 0 ? $payment->getIsTransactionPending() : false;

        $payment = $this->captureService->execute($payment, $amountToCapture);
        $isCaptureTransactionPending = $amountToCapture > 0 ? $payment->getIsTransactionPending() : false;

        //Check if one of the transaction type is pending.
        if ($isAuthorizeTransactionPending && !$isCaptureTransactionPending) {
            $payment->setIsTransactionPending(true);
            $payment->setIsTransactionApproved(false);
        } elseif (!$isAuthorizeTransactionPending && $isCaptureTransactionPending) {
            $payment->setIsTransactionPending(true);
            $payment->setIsTransactionApproved(false);
        }

        return $payment;
    }
}
