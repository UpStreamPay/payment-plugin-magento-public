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

namespace UpStreamPay\Core\Model;

use Magento\Framework\Api\SearchResults;
use UpStreamPay\Core\Api\Data\SubscriptionSearchResultsInterface;

/**
 * Class SubscriptionSearchResults
 *
 * @package UpStreamPay\Core\Model
 */
class SubscriptionSearchResults extends SearchResults implements SubscriptionSearchResultsInterface
{

}
