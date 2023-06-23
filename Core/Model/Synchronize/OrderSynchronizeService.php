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

namespace UpStreamPay\Core\Model\Synchronize;

use GuzzleHttp\Exception\GuzzleException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use NoTransactionsException;
use UpStreamPay\Client\Exception\NoOrderFoundException;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Model\CaptureService;

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
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly SynchronizeUpStreamPayPaymentData $synchronizeUpStreamPayPaymentData,
        private readonly CaptureService $captureService
    ) {
    }

    /**
     * Synchronize the UpStream Pay order with Magento custom payment & transaction table.
     *
     * @param OrderInterface $order
     *
     * @return void
     * @throws GuzzleException
     * @throws LocalizedException
     * @throws NoOrderFoundException
     * @throws NoSuchEntityException
     * @throws NoTransactionsException
     * @throws \JsonException
     */
    public function synchronizeAndCapture(OrderInterface $order): void
    {
        $quoteId = (int) $order->getQuoteId();
        $orderId = (int) $order->getEntityId();

        $orderTransactionsResponse = $this->client->getAllTransactionsForOrder($quoteId);

        if (count($orderTransactionsResponse) === 0) {
            throw new NoTransactionsException('No transactions found in API for the order with ID ' . $order->getId());
        }

        //Save UpStream Pay payment & transaction data to DB.
        $this->synchronizeUpStreamPayPaymentData->execute(
            $orderTransactionsResponse,
            $orderId,
            $quoteId,
            (int) $order->getPayment()->getEntityId()
        );

        //Capture & update order.
        $this->captureService->capture($order);
    }
}
