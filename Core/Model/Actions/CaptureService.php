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
use Magento\Payment\Model\MethodInterface;
use UpStreamPay\Core\Api\Data\OrderPaymentInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Exception\CaptureErrorException;
use UpstreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\OrderTransactions;
use Magento\Framework\Event\ManagerInterface as EventManager;

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
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param Config $config
     * @param EventManager $eventManager
     * @param FloatComparator $floatComparator
     */
    public function __construct(
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository,
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository,
        private readonly Config $config,
        private readonly EventManager $eventManager,
        private readonly FloatComparator $floatComparator
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
        $captures = [];

        //Get the capture transactions with a success status for the current order.
        $this->searchCriteriaBuilder->addFilter(
            OrderTransactionsInterface::TRANSACTION_TYPE,
            OrderTransactions::CAPTURE_ACTION
        )->addFilter(
            OrderTransactionsInterface::ORDER_ID,
            $payment->getOrder()->getEntityId()
        );

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $captureTransactions = $this->orderTransactionsRepository->getList($searchCriteria)->getItems();

        //If there are no capture transactions & we are using the order action then don't bother going any further.
        if ($this->config->getPaymentAction() === MethodInterface::ACTION_ORDER && count($captureTransactions) === 0) {
            return $payment;
        }

        //For each capture transaction check the status & determine what to do based on the result.
        foreach ($captureTransactions as $captureTransaction) {
            if ($upStreamPaySessionId === '') {
                $upStreamPaySessionId = $captureTransaction->getSessionId();
            }

            //In case of order action there will be no invoice here after place order.
            if ($payment->getCreatedInvoice() !== null) {
                $captureTransaction->setInvoiceId((int)$payment->getCreatedInvoice()->getEntityId());
                $this->orderTransactionsRepository->save($captureTransaction);
            }

            if ($captureTransaction->getStatus() === OrderTransactions::ERROR_STATUS) {
                $atLeastOneCaptureError = true;
            } elseif ($captureTransaction->getStatus() === OrderTransactions::SUCCESS_STATUS) {
                $amountCaptured += $captureTransaction->getAmount();
                $captures[$captureTransaction->getParentPaymentId()][] = $captureTransaction;
            } elseif ($captureTransaction->getStatus() === OrderTransactions::WAITING_STATUS) {
                $atLeastOneCaptureWaiting = true;
            }

            $payment->getOrder()->addCommentToStatusHistory(sprintf(
                'Transaction %s %s for %s with amount %s in status %s',
                $captureTransaction->getTransactionType(),
                $captureTransaction->getTransactionId(),
                $captureTransaction->getMethod(),
                $captureTransaction->getAmount(),
                $captureTransaction->getStatus()
            ));
        }

        //Get all payments related to the captures done above.
        $this->searchCriteriaBuilder->addFilter(
            OrderPaymentInterface::ENTITY_ID,
            array_keys($captures),
            'in'
        );

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $orderPayments = $this->orderPaymentRepository->getList($searchCriteria);

        //Set on each payment the total captured.
        foreach ($orderPayments->getItems() as $orderPayment) {
            if ($orderPayment->getAmountCaptured() < $orderPayment->getAmount()) {
                $totalCapturedPayment = 0.00;
                /** @var OrderTransactionsInterface $capture */
                foreach ($captures[$orderPayment->getEntityId()] as $capture) {
                    $totalCapturedPayment += $capture->getAmount();
                }

                $this->eventManager->dispatch('payment_usp_write_log', ['orderPayment' => $orderPayment]);
                $orderPayment->setAmountCaptured($orderPayment->getAmountCaptured() + $totalCapturedPayment);
                $this->orderPaymentRepository->save($orderPayment);
            }
        }

        //Every transaction has a capture success & the amount to capture matches the amount captured.
        //To avoid issue when comparing floats, use built-in magento feature (it uses an epsilon of 0.00001).
        if (!$atLeastOneCaptureError && !$atLeastOneCaptureWaiting && $this->floatComparator->equal($amountCaptured, $amount)) {
            //Every capture is a success, so the payment is captured.
            $payment
                ->setTransactionId($upStreamPaySessionId)
                ->setIsTransactionClosed(false)
                ->setIsTransactionPending(false)
                ->setIsTransactionApproved(true)
                ->setCurrencyCode($payment->getOrder()->getOrderCurrencyCode());
        } elseif ($atLeastOneCaptureWaiting) {
            //At least one transaction is in waiting, tell Magento that the payment is still pending.
            $payment->setIsTransactionPending(true);
        } elseif ($atLeastOneCaptureError && !$atLeastOneCaptureWaiting) {
            //There is at least one capture in error and no capture in waiting, so we can safely perform a refund on all
            //capture for this order.
            throw new CaptureErrorException(
                'At least one Capture transaction is in error, refund all Capture in success.'
            );
        }

        return $payment;
    }
}
