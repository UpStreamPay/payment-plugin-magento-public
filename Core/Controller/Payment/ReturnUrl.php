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

namespace UpStreamPay\Core\Controller\Payment;

use Magento\Checkout\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Processor;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Exception\AuthorizeErrorException;
use UpStreamPay\Core\Exception\CaptureErrorException;
use UpStreamPay\Core\Exception\OrderErrorException;
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
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        private readonly Session $checkoutSession,
        private readonly RedirectFactory $redirectFactory,
        private readonly LoggerInterface $logger,
        private readonly OrderManagementInterface $orderManagement,
        private readonly Config $config,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly Processor $paymentProcessor,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly ManagerInterface $messageManager
    ) {}

    /**
     * Handle the redirect coming from UpStream Pay.
     * //@TODO WIP, calling external class might be needed.
     *
     * @return ResultInterface
     * @throws LocalizedException
     */
    public function execute(): ResultInterface
    {
        $resultRedirect = $this->redirectFactory->create();

        try {
            $order = $this->checkoutSession->getLastRealOrder();
            $payment = $order->getPayment();

            if ($this->config->getPaymentAction() === MethodInterface::ACTION_AUTHORIZE) {
                $this->paymentProcessor->authorize($payment, true, $order->getBaseTotalDue());
                $this->orderRepository->save($order);
            } elseif ($this->config->getPaymentAction() === MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
                /** @var InvoiceInterface $invoice */
                //We can only have one invoice because we come back from the redirect url.
                $invoice = $order->getInvoiceCollection()->getFirstItem();
                $payment->setCreatedInvoice($invoice);
                $this->paymentProcessor->capture($payment, $invoice);

                //After capture is done trigger pay of the invoice.
                if ($invoice->getIsPaid()) {
                    $invoice->pay();
                }

                $this->orderRepository->save($order);
                $this->invoiceRepository->save($invoice);
            } elseif ($this->config->getPaymentAction() === MethodInterface::ACTION_ORDER) {
                $this->paymentProcessor->order($payment, $order->getBaseTotalDue());
                $this->orderRepository->save($order);
            }

            $resultRedirect->setPath('checkout/onepage/success', ['_secure' => true]);

            return $resultRedirect;
        } catch (AuthorizeErrorException | CaptureErrorException | OrderErrorException $exception) {
            //An authorize error has been found => we can deny the payment, it will cancel the order.
            $this->denyPayment($payment, $order);

            //Restore the user quote
            $this->orderRepository->save($order);
            $this->checkoutSession->restoreQuote();
        } catch (\Throwable $exception) {
            //In case of another exception, payment has to be denied but the quote can't be restored.
            //This catch means something went wrong on the API side (409 conflict for instance or other weird issue).
            //To avoid risks & having a cart that will produce errors down the line when we try to pay it's better
            //to not restore the quote & cancel the payment. This case should not happen very often.
            $this->logger->critical('Error while trying to handle the order after redirect from UpStream Pay');
            $this->logger->critical('Order ID was ' . $order->getEntityId());
            $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);

            $this->denyPayment($payment, $order);
            $this->orderRepository->save($order);
        }

        $this->messageManager->addErrorMessage($this->config->getErrorMessage());
        $resultRedirect->setPath('checkout/cart', ['_secure' => true]);

        return $resultRedirect;
    }

    /**
     * Deny the payment & handle exception.
     *
     * @param InfoInterface $payment
     * @param OrderInterface $order
     *
     * @return void
     */
    private function denyPayment(InfoInterface $payment, OrderInterface $order): void
    {
        try {
            $payment->deny();
        } catch (\Throwable $throwable) {
            //If even the denyPayment doesn't work (it could happen if the API is down) then log but nothing else.
            $this->logger->critical(
                'Error while trying to deny payment for the order after redirect from UpStream Pay'
            );
            $this->logger->critical('Order ID was ' . $order->getEntityId());
            $this->logger->critical(
                sprintf(
                    'All transactions for the order with ID %s must be refunded / canceled in UpStream Pay BO.',
                    $order->getQuoteId()
                )
            );
        }
    }
}
