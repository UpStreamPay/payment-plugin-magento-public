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

namespace UpStreamPay\Core\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use UpStreamPay\Core\Api\Data\PaymentMethodInterface;

/**
 * Class PaymentMethod
 *
 * @package UpStreamPay\Core\Model\ResourceModel
 */
class PaymentMethod extends AbstractDb
{
    /**
     * @inheritDoc
     */
    protected function _construct()
    {
        $this->_init('upstream_pay_payment_method', PaymentMethodInterface::ENTITY_ID);
    }
}
