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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use UpStreamPay\Core\Api\Data\SubscriptionInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Api\SubscriptionRepositoryInterface;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Subscription;
use UpStreamPay\Core\Model\SubscriptionFactory;

/**
 * Class SaveSubscriptionService
 *
 * @package UpStreamPay\Core\Model\Subscription
 */
class SaveSubscriptionService
{

    /**
     * @param SubscriptionFactory $subscriptionFactory
     * @param SubscriptionRepositoryInterface $subscriptionRepository
     * @param ProductRepositoryInterface $productRepository
     * @param ManagerInterface $eventManager
     * @param Config $config
     * @param LoggerInterface $logger
     * @param OrderTransactionsRepositoryInterface $orderTransactionsRepository
     */
    public function __construct(
        private readonly SubscriptionFactory $subscriptionFactory,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
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
     * @param int $invoiceId
     *
     * @return void
     */
    public function execute(OrderInterface $order, int $invoiceId): void
    {
        try {
            $transactionId = $this->orderTransactionsRepository->getByInvoiceIdAndPrimaryMethod($invoiceId);
        } catch (Throwable $exception) {
            $this->logger->critical('Error while trying to load a transaction for invoice with ID: ' . $invoiceId);
            $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);

            return;
        }

        $subscriptionEligibleAttrCode = $this->config->getSubscriptionPaymentProductSubscriptionAttributeCode();
        $subscriptionDurationAttrCode = $this->config->getSubscriptionPaymentProductSubscriptionDurationAttributeCode();

        foreach ($order->getItems() as $orderItem) {
            try {
                $product = $this->productRepository->getById($orderItem->getProductId());
            } catch (NoSuchEntityException $exception) {
                $this->logger->critical(
                    'Error while trying to load the product with ID: ' . $orderItem->getProductId()
                );
                $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);

                return;
            }
            $productSubDuration = $product->getData($subscriptionDurationAttrCode);
            /* check if there already is a subscription with that order and product */
            try {
                $existingSubscription = $this->subscriptionRepository->getBySubscriptionIdentifier(
                    $product->getSku() . '_' . $order->getIncrementId()
                );
            } catch (Throwable $exception) {
                $this->logger->critical(
                    'Error while trying to load an existing subscription for order: ' . $order->getIncrementId()
                );
                $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);

                return;
            }

            //At this point if we have no subscription found it means that this is the original order, not a renewal.
            if (!$existingSubscription || !$existingSubscription->getEntityId()) {
                if ($product->getData($subscriptionEligibleAttrCode) && isset($productSubDuration)) {
                    try {
                        $subscription = $this->createAndSaveSubscription(
                            $order,
                            $product,
                            $transactionId,
                            $subscriptionDurationAttrCode
                        );
                        $futureSubscription = $this->createAndSaveSubscription(
                            $order,
                            $product,
                            $transactionId,
                            $subscriptionDurationAttrCode,
                            true
                        );
                        $this->eventManager->dispatch(
                            'new_usp_subscription_saved',
                            [
                                'subscription' => $subscription,
                                'future_subscription' => $futureSubscription,
                            ]
                        );
                    } catch (Throwable $exception) {
                        $this->logger->critical(
                            'Error while trying to the subscriptions for order ' . $order->getIncrementId()
                        );
                        $this->logger->critical(
                            $exception->getMessage(),
                            [
                                'exception' => $exception->getTraceAsString(),
                            ]
                        );
                    }

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
     *
     * @return SubscriptionInterface
     * @throws LocalizedException
     */
    public function createAndSaveSubscription(
        $order,
        $product,
        string $transactionId,
        string $subscriptionDurationAttrCode,
        bool $future = false
    ): SubscriptionInterface
    {
        $subscription = $this->subscriptionFactory->create()
            ->setSubscriptionIdentifier($product->getSku() . '_' . $order->getIncrementId())
            ->setProductPrice((float)$product->getPrice())
            ->setProductName($product->getName())
            ->setProductSku($product->getSku())
            ->setOriginalTransactionId($transactionId);

        /* set first subscription dates */
        $startDate = date('Y-m-d', time());
        $endDate = date(
            'Y-m-d',
            strtotime($startDate . ' + ' . $product->getData($subscriptionDurationAttrCode) . ' days')
        );

        if (!$future) {
            $subscription
                ->setSubscriptionStatus(Subscription::ENABLED)
                ->setPaymentStatus(Subscription::PAID)
                ->setStartDate($startDate)
                ->setEndDate($endDate)
                ->setOrderId((int)$order->getEntityId());
        } else {
            $futureStartDate = date('Y-m-d', strtotime($endDate . ' + 1 days'));
            $futureEndDate = date(
                'Y-m-d',
                strtotime($futureStartDate . ' + ' . $product->getData($subscriptionDurationAttrCode) . ' days')
            );

            $subscription
                ->setSubscriptionStatus(Subscription::DISABLED)
                ->setPaymentStatus(Subscription::TO_PAY)
                ->setStartDate($futureStartDate)
                ->setEndDate($futureEndDate)
                ->setNextPaymentDate($futureEndDate);
        }

        $this->subscriptionRepository->save($subscription);

        return $subscription;
    }

}
