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
namespace UpStreamPay\Core\Api;

/**
 * Interface SessionInterface
 *
 */
interface SessionInterface
{
    /**
     * Return the session data (build the order) in order to indicate to UpStream Pay what payment methods to return.
     *
     * @return array
     */
    public function getSession(): array;
}
