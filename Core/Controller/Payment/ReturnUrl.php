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
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Processor;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Exception\AuthorizeErrorException;
use UpStreamPay\Core\Model\Config;

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
     * @param Session $checkoutSession
     * @param RedirectFactory $redirectFactory
     * @param LoggerInterface $logger
     * @param OrderManagementInterface $orderManagement
     * @param Config $config
     * @param OrderRepositoryInterface $orderRepository
     * @param Processor $paymentProcessor
     */
    public function __construct(
        private readonly Session $checkoutSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly LoggerInterface $logger,
        private readonly OrderManagementInterface $orderManagement,
        private readonly Config $config,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Processor $paymentProcessor
    ) {}

    /**
     * Handle the redirect coming from UpStream Pay.
     * //@TODO WIP, calling external class might be needed.
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $resultRedirect = $this->redirectFactory->create();

        try {
            $order = $this->checkoutSession->getLastRealOrder();
            $payment = $order->getPayment();

            if ($this->config->getPaymentAction() === MethodInterface::ACTION_AUTHORIZE) {
                $this->paymentProcessor->authorize($payment, true, $order->getTotalDue());
            } elseif ($this->config->getPaymentAction() === MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
                /** @var InvoiceInterface $invoice */
                //We can only have one invoice because we come back from the redirect url.
                $invoice = $order->getInvoiceCollection()->getFirstItem();
                $this->paymentProcessor->capture($payment, $invoice);
            }

            $resultRedirect->setPath('checkout/onepage/success', ['_secure' => true]);

            return $resultRedirect;
        } catch (AuthorizeErrorException $exception) {
            //@TODO WIP => this can only happen in authorize action for now. Check this when we implement order action.
            //An authorize error has been found => we can deny the payment, it will cancel the order.
            $payment->deny();
        } catch (\Throwable $exception) {
            $this->logger->critical('Error while trying to handle the order after redirect from UpStream Pay');
            $this->logger->critical('Order ID was ' . $order->getEntityId());
            $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);

            $this->orderManagement->cancel($order->getEntityId());
        }

        //Restore the user quote & redirect to cart.
        $this->orderRepository->save($order);
        $this->checkoutSession->restoreQuote();
        $resultRedirect->setPath('checkout/cart', ['_secure' => true]);

        return $resultRedirect;
    }
}
