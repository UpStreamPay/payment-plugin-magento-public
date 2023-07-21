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

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Invoice;
use UpStreamPay\Core\Model\Actions\CancelService;
use UpStreamPay\Core\Model\Config;

/**
 * Class CancelOrderObserver
 *
 * @package UpStreamPay\Core\Observer
 */
class CancelOrderObserver implements ObserverInterface
{
    /**
     * @param CancelService $cancelService
     * @param Config $config
     */
    public function __construct(
        private readonly CancelService $cancelService,
        private readonly Config $config
    ) {
    }

    /**
     * Cancel the transactions after Magento cancels the order.
     * Only trigger it if some conditions are met:
     * - Payment method is upstream_pay
     * - Payment action is the action_order
     * - Order has at least one invoice that isn't paid or no invoice
     * - Order cannot be voided (this is why Magento didn't cancel on its own)
     *
     * @see order_cancel_after
     *
     * @param Observer $observer
     *
     * @return void
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        /** @var OrderInterface $order */
        $order = $observer->getData('order');
        $payment = $order->getPayment();

        //No need to go any further if the payment method isn't upstream pay.
        if ($payment->getMethod() !== Config::METHOD_CODE_UPSTREAM_PAY) {
            return;
        }

        if ($this->orderHasTransactionsToCancel($order) && !$payment->canVoid()
            && $this->config->getPaymentAction() === MethodInterface::ACTION_ORDER) {
            //This will only cancel transactions not linked to a paid invoice.
            $this->cancelService->execute($order, false);
        }
    }

    /**
     * Return true if order has transactions to cancel, false otherwise.
     *
     * To be true an order must :
     * - have no invoice at all (meaning we just placed the order).
     * - at least one invoice in canceled state.
     * - the total due is > 0 meaning we need to receive more payments.
     *
     * We should not have invoice in pending state because otherwise the order would be in a Payment Review status.
     * Unless a wrong manual action is done we can only have paid or canceled invoices at this point.
     *
     * @param OrderInterface $order
     *
     * @return bool
     */
    private function orderHasTransactionsToCancel(OrderInterface $order): bool
    {
        $invoices = $order->getInvoiceCollection();

        if (count($invoices) === 0) {
            return true;
        }

        /** @var InvoiceInterface $invoice */
        foreach ($invoices as $invoice) {
            if ($invoice->getState() === Invoice::STATE_CANCELED) {
                return true;
            }
        }

        if ($order->getBaseTotalDue() > 0) {
            return true;
        }

        return false;
    }
}
