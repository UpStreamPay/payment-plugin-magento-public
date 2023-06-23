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

namespace UpStreamPay\Core\Model;

use Magento\Framework\Api\SearchResults;
use UpStreamPay\Core\Api\Data\OrderTransactionsSearchResultsInterface;

/**
 * Class OrderTransactionsSearchResults
 *
 * @package UpStreamPay\Core\Model
 */
class OrderTransactionsSearchResults extends SearchResults implements OrderTransactionsSearchResultsInterface
{

}
