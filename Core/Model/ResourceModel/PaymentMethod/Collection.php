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

namespace UpStreamPay\Core\Model\ResourceModel\PaymentMethod;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use UpStreamPay\Core\Model\PaymentMethod;
use UpStreamPay\Core\Model\ResourceModel\PaymentMethod as PaymentMethodResourceModel;

/**
 * Class Collection
 *
 * @codeCoverageIgnore
 *
 * @package UpStreamPay\Core\Model\ResourceModel\PaymentMethod
 */
class Collection extends AbstractCollection
{
    /**
     * @inheritDoc
     */
    protected function _construct(): void
    {
        $this->_init(PaymentMethod::class, PaymentMethodResourceModel::class);
    }
}
