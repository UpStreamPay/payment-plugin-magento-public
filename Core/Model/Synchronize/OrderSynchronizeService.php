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
use Magento\Framework\Math\FloatComparator;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;
use UpStreamPay\Client\Exception\NoSessionFoundException;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
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
use UpStreamPay\Core\Model\Session\PurseSessionDataManager;

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
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     * @param OrderTransactionsRepositoryInterface $orderTransactionsRepository
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
        private readonly CancelService $cancelService,
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository,
        private readonly FloatComparator $floatComparator
    )
    {
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
     * @throws NoSessionFoundException
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
        $purseSessionId = $payment->getData(PurseSessionDataManager::PAYMENT_PURSE_SESSION_ID);
        $orderId = (int)$payment->getParentId();

        if ($this->shouldSynchronizeWithPurse($action, $payment->getOrder())) {
            $sessionTransactionsResponse = $this->client->getAllTransactionsForSession($purseSessionId);

            if (count($sessionTransactionsResponse) === 0) {
                throw new NoTransactionsException(
                    'No transactions found in API for the session with ID ' . $purseSessionId
                );
            }

            //Save UpStream Pay payment & transaction data to DB.
            $this->synchronizeUpStreamPayPaymentData->execute(
                $sessionTransactionsResponse,
                $orderId,
                (int)$payment->getOrder()->getQuoteId(),
                (int)$payment->getEntityId(),
            );
        }

        if ($action === OrderTransactions::AUTHORIZE_ACTION) {
            $payment = $this->authorizeService->execute($payment, $amount);
        } elseif ($action === OrderTransactions::CAPTURE_ACTION) {
            $payment = $this->captureService->execute($payment, $amount);
        } elseif ($action === OrderTransactions::VOID_ACTION) {
            $payment = $this->voidService->execute($payment);
        } elseif ($action === OrderTransactions::REFUND_ACTION) {
            $payment = $this->refundService->execute($payment, $amount);
        } elseif ($action === OrderTransactions::ORDER_ACTION) {
            $payment = $this->orderService->execute($payment, $amount);
        } elseif ($action === OrderTransactions::ORDER_CAPTURE_ACTION) {
            $payment = $this->orderActionCaptureService->execute($payment, $amount);
        } elseif ($action === OrderTransactions::ORDER_CANCEL) {
            $this->cancelService->execute($payment->getOrder(), false);

            return $payment;
        }

        return $payment;
    }

    /**
     * @param Order $order
     *
     * @return bool
     */
    private function orderHasAllPursePayments(Order $order): bool
    {
        $orderPaymentsSum = 0;

        try {
            $orderPayments = $this->orderPaymentRepository->getByOrderId((int)$order->getEntityId());
        } catch (\Exception $exception) {
            return false;
        }

        foreach ($orderPayments as $orderPayment) {
            $orderPaymentsSum += $orderPayment->getAmount();
        }

        return $this->floatComparator->equal($orderPaymentsSum, (float)$order->getBaseGrandTotal());
    }

    /**
     * @param Order $order
     *
     * @return bool
     */
    private function orderHasAllPurseTransactions(Order $order): bool
    {
        $orderTransactionsSum = 0;

        try {
            $orderTransactions = $this->orderTransactionsRepository->getByOrderId((int)$order->getEntityId());
        } catch (\Exception $exception) {
            return false;
        }

        foreach ($orderTransactions as $orderTransaction) {
            if ($orderTransaction->getTransactionType() === OrderTransactions::AUTHORIZE_ACTION ||
                ($orderTransaction->getTransactionType() === OrderTransactions::CAPTURE_ACTION
                    && $orderTransaction->getParentTransactionId() === null)
            ) {
                $orderTransactionsSum += $orderTransaction->getAmount();
            }
        }

        return $this->floatComparator->equal($orderTransactionsSum, (float)$order->getBaseGrandTotal());
    }

    /**
     * @param $action
     * @param $order
     *
     * @return bool
     */
    private function shouldSynchronizeWithPurse($action, $order): bool
    {
        return !$this->orderHasAllPursePayments($order) && !$this->orderHasAllPurseTransactions($order)
            && $action !== OrderTransactions::VOID_ACTION && $action !== OrderTransactions::REFUND_ACTION;
    }
}
