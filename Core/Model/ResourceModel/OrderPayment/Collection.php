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

namespace UpStreamPay\Core\Model\ResourceModel\OrderPayment;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use UpStreamPay\Core\Model\ResourceModel\OrderPayment as OrderPaymentResource;
use UpStreamPay\Core\Model\OrderPayment as OrderPaymentModel;

/**
 * Class Collection
 *
 * @package UpStreamPay\Core\Model\ResourceModel\OrderPayment
 */
class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(OrderPaymentModel::class, OrderPaymentResource::class);
    }
}
