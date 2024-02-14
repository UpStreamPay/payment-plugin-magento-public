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

namespace UpStreamPay\Core\Model\ResourceModel\Subscription;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use UpStreamPay\Core\Model\ResourceModel\Subscription as SubscriptionResource;
use UpStreamPay\Core\Model\Subscription as SubscriptionModel;

/**
 * Class Collection
 *
 * @codeCoverageIgnore
 *
 * @package UpStreamPay\Core\Model\ResourceModel\Subscription
 */
class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(SubscriptionModel::class, SubscriptionResource::class);
    }
}
