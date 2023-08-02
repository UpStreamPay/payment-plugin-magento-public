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

namespace UpStreamPay\Core\Model\Synchronize;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use UpStreamPay\Client\Exception\NoOrderFoundException;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Exception\AuthorizeErrorException;
use UpStreamPay\Core\Exception\CaptureErrorException;
use UpStreamPay\Core\Exception\NoPaymentMethodFoundException;
use UpStreamPay\Core\Exception\NotEnoughFundException;
use UpStreamPay\Core\Exception\NoTransactionsException;
use UpStreamPay\Core\Exception\OrderErrorException;
use UpStreamPay\Core\Model\Actions\AuthorizeService;
use UpStreamPay\Core\Model\Actions\CancelService;
use UpStreamPay\Core\Model\Actions\CaptureService;
use UpStreamPay\Core\Model\Actions\OrderActionCaptureService;
use UpStreamPay\Core\Model\Actions\OrderService;
use UpStreamPay\Core\Model\Actions\RefundService;
use UpStreamPay\Core\Model\Actions\VoidService;
use UpStreamPay\Core\Model\OrderTransactions;

/**
 * Class OrderSynchronizeService
 *
 * @package UpStreamPay\Core\Model
 */
class OrderSynchronizeService
{
    /**
     * @param ClientInterface $client
     * @param SynchronizeUpStreamPayPaymentData $synchronizeUpStreamPayPaymentData
     * @param CaptureService $captureService
     * @param AuthorizeService $authorizeService
     * @param VoidService $voidService
     * @param RefundService $refundService
     * @param OrderService $orderService
     * @param OrderActionCaptureService $orderActionCaptureService
     * @param CancelService $cancelService
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly SynchronizeUpStreamPayPaymentData $synchronizeUpStreamPayPaymentData,
        private readonly CaptureService $captureService,
        private readonly AuthorizeService $authorizeService,
        private readonly VoidService $voidService,
        private readonly RefundService $refundService,
        private readonly OrderService $orderService,
        private readonly OrderActionCaptureService $orderActionCaptureService,
        private readonly CancelService $cancelService
    ) {
    }

    /**
     * Synchronize the order & execute the given action.
     *
     * @param InfoInterface $payment
     * @param float $amount
     * @param string $action
     *
     * @return InfoInterface
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws NoOrderFoundException
     * @throws NoTransactionsException
     * @throws JsonException
     * @throws AuthorizeErrorException
     * @throws CaptureErrorException
     * @throws OrderErrorException
     * @throws NotEnoughFundException
     * @throws NoPaymentMethodFoundException
     */
    public function execute(InfoInterface $payment, float $amount, string $action): InfoInterface
    {
        $quoteId = (int) $payment->getOrder()->getQuoteId();
        $orderId = (int) $payment->getParentId();

        if ($action !== OrderTransactions::VOID_ACTION && $action !== OrderTransactions::REFUND_ACTION) {
            $orderTransactionsResponse = $this->client->getAllTransactionsForOrder($quoteId);

            if (count($orderTransactionsResponse) === 0) {
                throw new NoTransactionsException('No transactions found in API for the order with ID ' . $orderId);
            }

            //Save UpStream Pay payment & transaction data to DB.
            $this->synchronizeUpStreamPayPaymentData->execute(
                $orderTransactionsResponse,
                $orderId,
                $quoteId,
                (int)$payment->getEntityId()
            );
        }

        if ($action === OrderTransactions::AUTHORIZE_ACTION) {
            return $this->authorizeService->execute($payment, $amount);
        } elseif ($action === OrderTransactions::CAPTURE_ACTION) {
           return  $this->captureService->execute($payment, $amount);
        } elseif ($action === OrderTransactions::VOID_ACTION) {
            return $this->voidService->execute($payment);
        } elseif ($action === OrderTransactions::REFUND_ACTION) {
            return $this->refundService->execute($payment, $amount);
        } elseif ($action === OrderTransactions::ORDER_ACTION) {
            return $this->orderService->execute($payment, $amount);
        } elseif ($action === OrderTransactions::ORDER_CAPTURE_ACTION) {
            return $this->orderActionCaptureService->execute($payment, $amount);
        } elseif ($action === OrderTransactions::ORDER_CANCEL) {
            $this->cancelService->execute($payment->getOrder(), false);

            return $payment;
        }

        return $payment;
    }
}