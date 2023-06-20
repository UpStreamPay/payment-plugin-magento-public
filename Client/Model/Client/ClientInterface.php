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
     */
    public function getAllTransactionsForOrder(int $orderId): array;
}
