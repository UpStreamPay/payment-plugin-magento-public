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

namespace UpStreamPay\Core\Exception;

use Exception;

/**
 * Class ConflictRetrieveTransactionsException
 *
 * @package UpStreamPay\Core\Exception
 *
 * This should only be triggered when getting a 409 http code when trying to retrieve transactions.
 */
class ConflictRetrieveTransactionsException extends Exception
{
}
