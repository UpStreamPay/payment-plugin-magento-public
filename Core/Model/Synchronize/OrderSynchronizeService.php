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
use Magento\Payment\Model\InfoInterface;
use UpStreamPay\Client\Exception\NoOrderFoundException;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Exception\AuthorizeErrorException;
use UpStreamPay\Core\Exception\CaptureErrorException;
use UpStreamPay\Core\Exception\NoTransactionsException;
use UpStreamPay\Core\Model\AuthorizeService;
use UpStreamPay\Core\Model\CaptureService;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\VoidService;

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
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly SynchronizeUpStreamPayPaymentData $synchronizeUpStreamPayPaymentData,
        private readonly CaptureService $captureService,
        private readonly AuthorizeService $authorizeService,
        private readonly VoidService $voidService
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
     * @throws \JsonException
     * @throws AuthorizeErrorException
     * @throws CaptureErrorException
     */
    public function execute(InfoInterface $payment, float $amount, string $action): InfoInterface
    {
        $quoteId = (int) $payment->getOrder()->getQuoteId();
        $orderId = (int) $payment->getParentId();

        $orderTransactionsResponse = $this->client->getAllTransactionsForOrder($quoteId);

        if (count($orderTransactionsResponse) === 0) {
            throw new NoTransactionsException('No transactions found in API for the order with ID ' . $orderId);
        }

        //Save UpStream Pay payment & transaction data to DB.
        $this->synchronizeUpStreamPayPaymentData->execute(
            $orderTransactionsResponse,
            $orderId,
            $quoteId,
            (int) $payment->getEntityId()
        );

        if ($action === OrderTransactions::AUTHORIZE_ACTION) {
            return $this->authorizeService->execute($payment, $amount);
        } elseif ($action === OrderTransactions::CAPTURE_ACTION) {
           return  $this->captureService->execute($payment, $amount);
        } elseif ($action === OrderTransactions::VOID_ACTION) {
            return $this->voidService->execute($payment);
        }
    }
}
