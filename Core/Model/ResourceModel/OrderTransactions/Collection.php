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

namespace UpStreamPay\Core\Model\ResourceModel\OrderTransactions;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use UpStreamPay\Core\Model\ResourceModel\OrderTransactions as OrderTransactionsResource;
use UpStreamPay\Core\Model\OrderTransactions as OrderTransactionsModel;

/**
 * Class Collection
 *
 * @package UpStreamPay\Core\Model\ResourceModel\OrderTransactions
 */
class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(OrderTransactionsModel::class, OrderTransactionsResource::class);
    }
}
