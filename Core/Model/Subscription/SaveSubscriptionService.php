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

namespace UpStreamPay\Core\Model\Subscription;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use UpStreamPay\Core\Api\Data\SubscriptionInterface;
use UpStreamPay\Core\Exception\NoTransactionsException;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Subscription;
use UpStreamPay\Core\Model\SubscriptionFactory;
use UpStreamPay\Core\Model\SubscriptionRepository;
use Throwable;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;

/**
 * Class SaveSubscriptionService
 *
 * @package UpStreamPay\Core\Model\Actions
 */
class SaveSubscriptionService
{

    /**
     * @param SubscriptionFactory $subscriptionFactory
     * @param SubscriptionRepository $subscriptionRepository
     * @param ProductRepositoryInterface $productRepository
     * @param ManagerInterface $eventManager
     * @param Config $config
     * @param LoggerInterface $logger
     * @param OrderTransactionsRepositoryInterface $orderTransactionsRepository
     */
    public function __construct(
        private readonly SubscriptionFactory $subscriptionFactory,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ManagerInterface $eventManager,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository
    )
    {
    }

    /**
     * @param OrderInterface $order
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute(OrderInterface $order, int $invoiceId): void
    {
        try {
            $transactionId = $this->orderTransactionsRepository->getByInvoiceIdAndPrimaryMethod($invoiceId);
        } catch (NoTransactionsException $exception) {
            $this->logger->critical('Error while trying to load a transaction');
            $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);
        }

        $subscriptionEligibleAttrCode = $this->config->getSubscriptionPaymentProductSubscriptionAttributeCode();
        $subscriptionDurationAttrCode = $this->config->getSubscriptionPaymentProductSubscriptionDurationAttributeCode();

        foreach ($order->getItems() as $orderItem) {
            $product = $this->productRepository->getById($orderItem->getProductId());
            $productSubDuration = $product->getData($subscriptionDurationAttrCode);
            /* check if there already is a subscription with that order and product */
            try {
                $existingSubscription = $this->subscriptionRepository->getBySubscriptionIdentifier($product->getSku() . '_' . $order->getIncrementId());
            } catch (Throwable $exception) {
                $this->logger->critical('Error while trying to load an existing subscription');
                $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);
            }
            if (!$existingSubscription || !$existingSubscription->getEntityId()) {
                if ($product->getData($subscriptionEligibleAttrCode) && isset($productSubDuration)) {
                    $subscription = $this->createAndSaveSubscription($order, $product, $transactionId, $subscriptionDurationAttrCode);
                    $futureSubscription = $this->createAndSaveSubscription($order, $product, $transactionId, $subscriptionDurationAttrCode, true);
                    $this->eventManager->dispatch('new_usp_subscription_saved', ['subscription' => $subscription, 'future_subscription' => $futureSubscription]);
                }
            }
        }
    }

    /**
     * @param $order
     * @param $product
     * @param string $transactionId
     * @param string $subscriptionDurationAttrCode
     * @param bool $future
     * @return SubscriptionInterface
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createAndSaveSubscription($order, $product, string $transactionId, string $subscriptionDurationAttrCode, bool $future = false): SubscriptionInterface
    {
        $subscription = $this->subscriptionFactory->create()
            ->setSubscriptionIdentifier($product->getSku() . '_' . $order->getIncrementId())
            ->setProductPrice((float)$product->getPrice())
            ->setProductName($product->getName())
            ->setProductSku($product->getSku())
            ->setOrderId((int) $order->getEntityId())
            ->setOriginalTransactionId($transactionId);

        /* set first subscription dates */
        $startDate = date('Y-m-d', time());
        $endDate = date('Y-m-d', strtotime($startDate . ' + ' . $product->getData($subscriptionDurationAttrCode) . ' days'));

        if (!$future) {
            $subscription
                ->setSubscriptionStatus(Subscription::ENABLED)
                ->setPaymentStatus(Subscription::PAID)
                ->setStartDate($startDate)
                ->setEndDate($endDate);
        } else {
            $futureStartDate = date('Y-m-d', strtotime($endDate . ' + 1 days'));
            $futureEndDate = date('Y-m-d', strtotime($futureStartDate . ' + ' . $product->getData($subscriptionDurationAttrCode) . ' days'));

            $subscription
                ->setSubscriptionStatus(Subscription::DISABLED)
                ->setPaymentStatus(Subscription::TO_PAY)
                ->setStartDate($futureStartDate)
                ->setEndDate($futureEndDate)
                ->setNextPaymentDate($futureEndDate);
        }

        try {
            $this->subscriptionRepository->save($subscription);
        } catch (Throwable $exception) {
            $this->logger->critical('Error while trying to save a subscription');
            $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);
        }

        return $subscription;
    }

}
