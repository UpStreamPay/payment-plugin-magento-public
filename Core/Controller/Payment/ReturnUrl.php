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

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Model\Synchronize\OrderSynchronizeService;

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
     * @param OrderSynchronizeService $orderSynchronizeService
     * @param Session $checkoutSession
     */
    public function __construct(
        private readonly OrderSynchronizeService $orderSynchronizeService,
        private readonly Session $checkoutSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly LoggerInterface $logger,
        private readonly OrderManagementInterface $orderManagement
    ) {}

    /**
     * Handle the redirect coming from UpStream Pay.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $resultRedirect = $this->redirectFactory->create();

        try {
            $order = $this->checkoutSession->getLastRealOrder();
            $this->orderSynchronizeService->synchronizeAndCapture($order);

            $resultRedirect->setPath('checkout/onepage/success', ['_secure' => true]);
        } catch (\Throwable $exception) {
            $this->logger->critical('Error while trying to handle the order after redirect from UpStream Pay');
            $this->logger->critical('Order ID was ' . $order->getEntityId());
            $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);

            //Restore the user quote & redirect to cart.
            $this->checkoutSession->restoreQuote();
            $this->orderManagement->cancel($order->getEntityId());
            $resultRedirect->setPath('checkout/cart', ['_secure' => true]);
        }

        return $resultRedirect;
    }
}
