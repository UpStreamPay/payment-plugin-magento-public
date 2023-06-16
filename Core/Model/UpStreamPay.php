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

use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\AbstractMethod;

/**
 * Class UpStreamPay
 *
 * @package Model
 */
class UpStreamPay extends AbstractMethod
{
    protected $_code = Config::METHOD_CODE_UPSTREAM_PAY;

    protected $_isGateway = true;

    protected $_canOrder = true;

    protected $_canAuthorize = true;

    protected $_canCapture = true;
    protected $_canCapturePartial = true;

    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;

    protected $_canVoid = true;

    protected $_canUseCheckout = true;

    protected $_canUseInternal = false;

    protected $_canFetchTransactionInfo = false;

    protected $_canReviewPayment = false;

    /**
     * @return string
     */
    public function getPaymentAction(): string
    {
        return AbstractMethod::ACTION_AUTHORIZE_CAPTURE;
    }

    public function authorize(InfoInterface $payment, $amount)
    {
        $payment->setIsTransactionPending(true);

        return $this;
    }

    public function capture(InfoInterface $payment, $amount)
    {
        $payment->setIsTransactionPending(true);

        return $this;
    }
}
