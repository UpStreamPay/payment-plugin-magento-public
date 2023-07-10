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
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Psr\Log\LoggerInterface;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Api\Data\OrderPaymentInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Exception\ConflictRetrieveTransactionsException;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\PaymentFinder\AllTransactionsFinder;

/**
 * Class VoidService
 *
 * @package UpStreamPay\Core\Model
 */
class VoidService
{
    /**
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ClientInterface $client
     * @param OrderTransactions $orderTransactions
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param Config $config
     * @param AllTransactionsFinder $findAllTransactions
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ClientInterface $client,
        private readonly OrderTransactions $orderTransactions,
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository,
        private readonly Config $config,
        private readonly AllTransactionsFinder $findAllTransactions,
        private readonly LoggerInterface $logger
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
        if ($this->config->getPaymentAction() === MethodInterface::ACTION_AUTHORIZE) {
            return $this->voidAllAuthorizeTransactions($payment);
        } elseif ($this->config->getPaymentAction() === MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
            return $this->voidAllCaptureTransactions($payment);
        } elseif ($this->config->getPaymentAction() === MethodInterface::ACTION_ORDER) {
            $payment = $this->voidAllAuthorizeTransactions($payment);
            return $this->voidAllCaptureTransactions($payment);
        }
    }

    /**
     * Void all authorize transactions.
     *
     * @param InfoInterface $payment
     *
     * @return InfoInterface
     * @throws LocalizedException
     */
    private function voidAllAuthorizeTransactions(InfoInterface $payment): InfoInterface
    {
        //Get the authorized transactions with a success status for the current order.
        $authorizeTransactions = $this->findAllTransactions->execute(
            OrderTransactions::AUTHORIZE_ACTION,
            (int)$payment->getOrder()->getEntityId(),
            OrderTransactions::SUCCESS_STATUS
        );

        //If there are no authorize transactions then there is no need to go further.
        if (count($authorizeTransactions) === 0) {
            return $payment;
        }

        foreach ($authorizeTransactions as $authorizeTransaction) {
            $body = [
                'order' => [
                    'amount' => $payment->getOrder()->getGrandTotal(),
                    'currency_code' => $payment->getOrder()->getOrderCurrencyCode(),
                ],
                'amount' => $authorizeTransaction->getAmount(),
            ];

            try {
                //Void from API.
                $voidResponse = $this->client->void($authorizeTransaction->getTransactionId(), $body);
            } catch (ConflictRetrieveTransactionsException $exception) {
                //A conflict on the API call has been found, don't block the process. This is not a standard error
                //& it should not happen most of the time. In case it does, try to void as many transactions as
                //we can.
                continue;
            }

            if ($this->config->getIsDebugEnabled()) {
                $this->logger->debug(
                    sprintf(
                        'Payment denied for order %s, void transaction response:',
                        $payment->getOrder()->getEntityId()
                    )
                );
                $this->logger->debug(print_r($voidResponse, true));
            }

            //Save the void transaction in DB.
            $voidTransaction = $this->orderTransactions->createTransactionFromResponse(
                $voidResponse,
                (int) $payment->getOrder()->getEntityId(),
                (int) $payment->getOrder()->getQuoteId(),
                (int) $authorizeTransaction->getParentPaymentId()
            );

            $payment->getOrder()->addCommentToStatusHistory(sprintf(
                'Voiding authorize transaction %s for payment %s with void transaction %s',
                $authorizeTransaction->getTransactionId(),
                $authorizeTransaction->getMethod(),
                $voidTransaction->getTransactionId()
            ));
        }

        return $payment;
    }

    /**
     * Void all Capture transactions.
     *
     * A capture transaction has to be refunded on the API.
     * In this scenario this takes place if an error is detected right after the place order.
     *
     * @param InfoInterface $payment
     *
     * @return InfoInterface
     * @throws LocalizedException
     */
    private function voidAllCaptureTransactions(InfoInterface $payment): InfoInterface
    {
        $refunds = [];

        //Get the captured transactions with a success status for the current order.
        $captureTransactions = $this->findAllTransactions->execute(
            OrderTransactions::CAPTURE_ACTION,
            (int)$payment->getOrder()->getEntityId(),
            OrderTransactions::SUCCESS_STATUS
        );

        //If there are no capture transactions then there is no need to go further.
        if (count($captureTransactions) === 0) {
            return $payment;
        }

        /** @var OrderTransactionsInterface $captureTransaction */
        foreach ($captureTransactions as $captureTransaction) {
            $body = [
                'order' => [
                    'amount' => $payment->getOrder()->getGrandTotal(),
                    'currency_code' => $payment->getOrder()->getOrderCurrencyCode(),
                ],
                'amount' => $captureTransaction->getAmount(),
            ];

            try {
                //Refund from API.
                $refundResponse = $this->client->refund($captureTransaction->getTransactionId(), $body);
            } catch (ConflictRetrieveTransactionsException $exception) {
                //A conflict on the API call has been found, don't block the process. This is not a standard error
                //& it should not happen most of the time. In case it does, try to void as many transactions as
                //we can.
                continue;
            }

            if ($this->config->getIsDebugEnabled()) {
                $this->logger->debug(
                    sprintf(
                        'Payment refunded for order %s, refund transaction response:',
                        $payment->getOrder()->getEntityId()
                    )
                );
                $this->logger->debug(print_r($refundResponse, true));
            }

            //Save the refund transaction in DB.
            $refundTransaction = $this->orderTransactions->createTransactionFromResponse(
                $refundResponse,
                (int) $payment->getOrder()->getEntityId(),
                (int) $payment->getOrder()->getQuoteId(),
                (int) $captureTransaction->getParentPaymentId(),
                $captureTransaction->getInvoiceId()
            );

            $payment->getOrder()->addCommentToStatusHistory(sprintf(
                'Voiding capture transaction %s & refunding %s with refund transaction %s for payment %s',
                $captureTransaction->getTransactionId(),
                $refundTransaction->getAmount(),
                $refundTransaction->getTransactionId(),
                $refundTransaction->getMethod(),
            ));

            if ($refundTransaction->getStatus() === OrderTransactions::ERROR_STATUS) {
                $errorMessage = sprintf(
                    'Transaction refund with ID %s for amount %s is in error, refund it in UpStream admin panel.',
                    $refundTransaction->getTransactionId(),
                    $refundTransaction->getAmount()
                );
                $this->logger->critical($errorMessage);
                $payment->getOrder()->addCommentToStatusHistory($errorMessage);
            }

            $refunds[$refundTransaction->getParentPaymentId()][] = $refundTransaction;
        }

        //Get all payment related to the refunds done above.
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

            $orderPayment->setAmountRefunded($orderPayment->getAmountRefunded() + $totalRefundForPayment);
            $this->orderPaymentRepository->save($orderPayment);
        }

        return $payment;
    }
}
