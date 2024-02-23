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

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Invoice;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Subscription\SaveSubscriptionService;

/**
 * Class CancelOrderObserver
 *
 * @codeCoverageIgnore
 *
 * @package UpStreamPay\Core\Observer
 */
class SaveSubscriptionObserver implements ObserverInterface
{
    /**
     * @param SaveSubscriptionService $saveSubscriptionService
     * @param Config $config
     * @param ProductRepositoryInterface $productRepository
     * @param LoggerInterface $logger
     * @param OrderRepositoryInterface $orderRepository
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoManagementInterface $creditmemoManagement
     */
    public function __construct(
        private readonly SaveSubscriptionService $saveSubscriptionService,
        private readonly Config $config,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LoggerInterface $logger,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CreditmemoFactory $creditmemoFactory,
        private readonly CreditmemoManagementInterface $creditmemoManagement
    ) {
    }

    /**
     * Save a subscription considering several conditions
     * - Subcsription is active
     * - Payment method is upstream_pay
     * - Payment action is the action_order
     * - Invoice has at least one product eligible
     *
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var Invoice $invoice */
        $invoice = $observer->getData('invoice');
        /** @var Order $order */
        $order = $observer->getData('order');

        try {
            if ($this->config->getSubscriptionPaymentEnabled()) {
                if (
                    $order->getPayment()->getMethod() === Config::METHOD_CODE_UPSTREAM_PAY &&
                    $this->hasSubscriptionEligibleProducts($order) &&
                    $this->config->getPaymentAction() === MethodInterface::ACTION_ORDER &&
                    $invoice->getState() === Invoice::STATE_PAID
                ) {
                    $this->saveSubscriptionService->execute($order, (int)$invoice->getEntityId());
                }
            }
        } catch (\Throwable $exception) {
            //In case we can't create the subscriptions for the invoice we just paid, we must refund & cancel the invoice.
            $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);
            $this->logger->critical('Error while trying to create subscription for invoice with ID ' . $invoice->getEntityId());
            $order->addCommentToStatusHistory($exception->getMessage());
            $order->addCommentToStatusHistory(
                'Error while trying to create subscription for invoice with ID ' . $invoice->getEntityId()
            );
            $this->orderRepository->save($invoice->getOrder());

            //Refund the invoice.
            $creditmemo = $this->creditmemoFactory->createByInvoice($invoice, $invoice->getData());

            foreach ($creditmemo->getAllItems() as $creditmemoItem) {
                $creditmemoItem->setBackToStock(true);
            }

            $this->creditmemoManagement->refund($creditmemo);
        }
    }

    /**
     * @param $order
     *
     * @return bool
     * @throws NoSuchEntityException
     */
    public function hasSubscriptionEligibleProducts($order): bool
    {
        foreach ($order->getItems() as $orderItem) {
            $product = $this->productRepository->getById($orderItem->getProductId());
            $productSubscriptionEligibe = $product->getData(
                $this->config->getSubscriptionPaymentProductSubscriptionAttributeCode()
            );
            $productSubDuration = $product->getData(
                $this->config->getSubscriptionPaymentProductSubscriptionDurationAttributeCode()
            );

            if ($productSubscriptionEligibe && null !== $productSubDuration && $productSubDuration > 0) {
                return true;
            }
        }

        return false;
    }
}
