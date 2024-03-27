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

namespace UpStreamPay\Core\Observer;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment\Processor;
use Psr\Log\LoggerInterface;
use Throwable;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Model\Actions\CancelService;
use UpStreamPay\Core\Model\Config;

/**
 * Class PayInvoiceObserver
 *
 * @package UpStreamPay\Core\Observer
 */
class PayInvoiceObserver implements ObserverInterface
{
    /**
     * @param Config $config
     * @param Processor $paymentProcessor
     * @param OrderRepositoryInterface $orderRepository
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param OrderTransactionsRepositoryInterface $transactionsRepository
     * @param LoggerInterface $logger
     * @param CancelService $cancelService
     */
    public function __construct(
        private readonly Config $config,
        private readonly Processor $paymentProcessor,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly OrderTransactionsRepositoryInterface $transactionsRepository,
        private readonly LoggerInterface $logger,
        private readonly CancelService $cancelService,
        private readonly ManagerInterface $eventManager
    ) {
    }

    /**
     * Trigger the payment of the invoice.
     * This can only happen after an invoice has been created & we are using the order action mode and the invoice
     * has not been paid yet and there are no transactions linked to that invoice.
     *
     * @see sales_order_invoice_save_after
     *
     * @param Observer $observer
     *
     * @return void
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        /** @var Invoice $invoice */
        $invoice = $observer->getData('invoice');
        $order = $invoice->getOrder();
        $payment = $order->getPayment();
        $payment->setCreatedInvoice($invoice);
        $method = $payment->getMethod();

        //No need to go any further if the payment method isn't upstream pay.
        if ($method !== Config::METHOD_CODE_UPSTREAM_PAY) {
            return;
        }

        //Only enter this condition if
        //- the order action mode on the payment method is used.
        //- the invoice has not been paid yet.
        //- there are no upstream pay transactions on the invoice.
        if ($this->config->getPaymentAction() === MethodInterface::ACTION_ORDER
            && !$invoice->getIsPaid() && (int)$invoice->getState() === Invoice::STATE_OPEN
            && !$this->invoiceHasUpstreamTransactions($invoice)) {
            try {
                $this->paymentProcessor->capture($payment, $invoice);

                //After capture is done trigger pay of the invoice.
                if ($invoice->getIsPaid()) {
                    $invoice->pay();
                }

                $this->orderRepository->save($order);
                $this->invoiceRepository->save($invoice);

                $this->eventManager->dispatch(
                    'usp_create_subscription_from_invoice',
                    [
                        'invoice' => $invoice,
                        'order' => $order
                    ]
                );
            } catch (Throwable $exception) {
                $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);
                $order->addCommentToStatusHistory($exception->getMessage());

                //Cancel the invoice we are trying tp pay.
                $invoice->cancel();
                $this->invoiceRepository->save($invoice);
                $this->orderRepository->save($invoice->getOrder());

                //Refund & void the transactions in UpStream Pay & cancel the order.
                $this->cancelService->execute($order);
            }
        }
    }

    /**
     * Returns true if the invoice has at least one upstream pay transaction linked to it.
     *
     * @param Invoice $invoice
     *
     * @return bool
     * @throws LocalizedException
     */
    private function invoiceHasUpstreamTransactions(Invoice $invoice): bool
    {
        return count($this->transactionsRepository->getByInvoiceId((int)$invoice->getEntityId())) > 0;
    }
}
