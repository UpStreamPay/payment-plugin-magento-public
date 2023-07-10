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

namespace UpStreamPay\Client\Model\Token;

/**
 * Class TokenValidator
 *
 * @package UpstreamPay\Client\Model\Token
 */
class TokenValidator
{
    public function validate(array $tokenData): bool
    {
        return true;
    }
}
