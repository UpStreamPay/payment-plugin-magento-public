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

namespace UpStreamPay\Client\Model\Client;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use UpStreamPay\Client\Exception\NoOrderFoundException;

/**
 * Interface ClientInterface
 *
 */
interface ClientInterface
{
    /**
     * Get token to authenticate on further API calls.
     * Return the raw token array without any changes.
     *
     * @return array
     */
    public function getToken(): array;

    /**
     * Create UpStream Pay session.
     * Return the full session without any changes.
     *
     * @param array $orderSession
     *
     * @return array
     */
    public function createSession(array $orderSession): array;

    /**
     * Get each transaction made for an order.
     * This will return every transaction no matter the type & status.
     *
     * @param int $orderId
     *
     * @return array
     *
     * @throws NoOrderFoundException
     * @throws JsonException
     * @throws GuzzleException
     */
    public function getAllTransactionsForOrder(int $orderId): array;

    /**
     * Capture the given transaction through transaction ID & body parameters.
     *
     * The body contains the amount to capture:
     * {
     *      "order": {
     *          "amount": 271.92,
     *          "currency_code": "EUR"
     *      },
     *      "amount": 270.92
     * }
     *
     * @param string $transactionId
     * @param array $body
     *
     * @return array
     * @throws GuzzleException
     * @throws \JsonException
     */
    public function capture(string $transactionId, array $body): array;

    /**
     * Void the given transaction through transaction ID & body parameters.
     *
     * The body contains the amount to void:
     * {
     *      "order": {
     *          "amount": 271.92,
     *          "currency_code": "EUR"
     *      },
     *      "amount": 270.92
     * }
     *
     * @param string $transactionId
     * @param array $body
     *
     * @return array
     */
    public function void(string $transactionId, array $body): array;
}
