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

namespace UpStreamPay\Client\Model\Client;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use UpStreamPay\Client\Exception\NoSessionFoundException;
use UpStreamPay\Core\Exception\ConflictRetrieveTransactionsException;

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
     * Create UpStream Pay wallet session.
     *
     * @param int $customerId
     *
     * @return array
     * @throws GuzzleException
     * @throws JsonException
     */
    public function createWalletSession(int $customerId): array;

    /**
     * Get each transaction made for a session.
     * This will return every transaction no matter the type & status.
     *
     * @param string $sessionId
     *
     * @return array
     *
     * @throws NoSessionFoundException
     * @throws JsonException
     * @throws GuzzleException
     */
    public function getAllTransactionsForSession(string $sessionId): array;

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
     * @throws JsonException
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
     * @throws GuzzleException
     * @throws JsonException
     * @throws ConflictRetrieveTransactionsException
     */
    public function void(string $transactionId, array $body): array;

    /**
     * Refund the given transaction through transaction ID & body parameters.
     *
     * The body contains the amount to refund:
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
     * @throws JsonException
     * @throws ConflictRetrieveTransactionsException
     */
    public function refund(string $transactionId, array $body): array;

    /**
     * Duplicate the given authorize transaction.
     * The new authorize transaction will be linked to the same session as the given authorize transaction.
     *
     * @param string $transactionId
     * @param array $body
     *
     * @return array
     * @throws GuzzleException
     * @throws JsonException
     */
    public function duplicate(string $transactionId, array $body): array;
}
