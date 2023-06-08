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

use Magento\Payment\Model\Method\AbstractMethod;

/**
 * Class UpStreamPay
 *
 * @package Model
 */
class UpStreamPay extends AbstractMethod
{
    protected $_code = Config::METHOD_CODE_UPSTREAM_PAY;

    protected $_isGateway = false;

    protected $_canOrder = true;

    protected $_canAuthorize = false;

    protected $_canCapture = false;

    protected $_canRefund = false;

    protected $_canVoid = false;

    protected $_canUseCheckout = true;

    public function isActive($storeId = null)
    {
        return true;
    }

    public function isAvailable(\Magento\Quote\Api\Data\CartInterface $quote = null)
    {
        return true;
    }
}
