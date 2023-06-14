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

namespace UpStreamPay\Core\Controller\Payment;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Class ReturnUrl
 *
 * @package UpStreamPay\Core\Controller\Payment
 *
 * @see base_url/upstreampay/payment/returnurl
 */
class ReturnUrl implements HttpGetActionInterface
{
    public const URL_PATH = 'upstreampay/payment/returnurl';

    /**
     * @param ResultFactory $resultFactory
     */
    public function __construct(
        private readonly ResultFactory $resultFactory
    ) {}

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setData(['message' => 'Coming from the returnUrl field after payment.']);

        return $result;
    }
}
