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

use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Api\Data\SubscriptionInterface;
use UpStreamPay\Core\Model\Subscription;
use UpStreamPay\Core\Model\Subscription\Renew\CartManagement;
use UpStreamPay\Core\Model\SubscriptionRepository;

/**
 * Class RenewSubscriptionService
 *
 * @package UpStreamPay\Core\Model\Subscription
 */
class RenewSubscriptionService
{

    /**
     * @param SubscriptionRepository $subscriptionRepository
     * @param ProductRepository $productRepository
     * @param EventManager $eventManager
     * @param CartManagement $cartManagementRenew
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     */
    public function __construct
    (
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly ProductRepository $productRepository,
        private readonly EventManager $eventManager,
        private readonly CartManagement $cartManagementRenew,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly LoggerInterface $logger
    )
    {
    }

    /**
     * @param SubscriptionInterface $subscription
     * @return void
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function execute(SubscriptionInterface $subscription): void
    {
        $product = $this->productRepository->get($subscription->getProductSku());

        /**
         * Check Price Variation
         */
        if ($subscription->getProductPrice() !== $product->getPrice()) {
            $subscription->setProductPrice($product->getPrice());
            $this->subscriptionRepository->save($subscription);

            $this->eventManager->dispatch('subscription_usp_price_update', [
                'newProductPrice' => $product->getPrice(),
                'subscription' => $subscription,
                'sku' => $product->getSku()
            ]);
        }

        //TODO We have a subscription list, we need one subscription. The following code will not work.
        $parentSubscription = $this->subscriptionRepository->getParentSubscription($subscription);
        $order = $this->orderRepository->get($parentSubscription->getOrderId());

        try {
            $quote = $this->cartManagementRenew->execute($subscription->getProductSku(), $order->getQuoteId());
        } catch (\Throwable $exception) {
            //In case of error while creating the quote, cancel the subscription to renew & don't process any further.
            $this->cancelSubscription($subscription);
            $this->logger->critical(
                'The quote could not be created, no payment has been made & the subscription has been canceled.'
            );
            $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);

            return;
        }

        try {
            $order = $this->cartManagementRenew->submitQuote($quote);
        } catch (\Throwable $exception) {
            //In case of error while creating the order, cancel the subscription to renew & don't process any further.
            $this->cancelSubscription($subscription);

            $this->logger->critical(
                'The order could not be created, no payment has been made & the subscription has been canceled.'
            );
            $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);

            return;
        }
    }

    /**
     * @param SubscriptionInterface $subscription
     *
     * @return void
     * @throws LocalizedException
     */
    private function cancelSubscription(SubscriptionInterface $subscription): void
    {
        $subscription
            ->setSubscriptionStatus(Subscription::CANCELED)
            ->setPaymentStatus(Subscription::CANCELED)
            ->setNextPaymentDate(null)
        ;

        $this->subscriptionRepository->save($subscription);

        $this->logger->critical(
            'There was an error while trying to renew the subscription with ID: ' . $subscription->getEntityId()
        );
    }
}
