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
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Psr\Log\LoggerInterface;
use Throwable;
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
     */
    public function __construct(
        private readonly SaveSubscriptionService $saveSubscriptionService,
        private readonly Config $config,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Save a subscription considering several conditions
     * - Subcsription is active
     * - Payment method is upstream_pay
     * - Payment action is the action_order
     * - Invoice has at least one product eligible
     * @param Observer $observer
     *
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if ($this->config->getSubscriptionPaymentEnabled()) {
            /** @var InvoiceInterface $invoice */
            $invoice = $observer->getData('invoice');
            $order = $invoice->getOrder();

            if (
                $order->getPayment()->getMethod() === Config::METHOD_CODE_UPSTREAM_PAY &&
                $this->hasSubscriptionEligibleProducts($order) &&
                $this->config->getPaymentAction() === MethodInterface::ACTION_ORDER
            ) {
                $this->saveSubscriptionService->execute($order, (int)$invoice->getEntityId());
            }
        }
    }

    /**
     * @param $order
     *
     * @return bool
     */
    public function hasSubscriptionEligibleProducts($order): bool
    {
        foreach ($order->getItems() as $orderItem) {
            try {
                $product = $this->productRepository->getById($orderItem->getProductId());
            } catch (Throwable $exception) {
                $this->logger->critical('Error while trying to load a product');
                $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);

                return false;
            }

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
