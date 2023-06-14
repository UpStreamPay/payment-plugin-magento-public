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
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;

/**
 * Class Notification
 *
 * @package UpStreamPay\Core\Controller\Payment
 *
 * @see base_url/upstreampay/payment/notification
 */
class Notification implements HttpGetActionInterface
{
    public const URL_PATH = 'upstreampay/payment/notification';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly ResultFactory $resultFactory
    ) {}

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $params = $this->request->getParams();

        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $result->setData(['message' => 'Payment notification received', 'params' => $params]);

        return $result;
    }
}
