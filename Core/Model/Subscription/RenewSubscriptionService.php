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

namespace UpStreamPay\Core\Model\Subscription;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use UpStreamPay\Core\Api\Data\SubscriptionInterface;
use UpStreamPay\Core\Model\SubscriptionRepository;
use Magento\Catalog\Model\ProductRepository;
use Magento\Framework\Event\ManagerInterface as EventManager;

class RenewSubscriptionService
{

    public function __construct
    (
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly ProductRepository      $productRepository,
        private readonly EventManager           $eventManager
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

        $parentSubscription = $this->subscriptionRepository->getParentSubscription($subscription);

        /**
         * Duplicate quote/order
         */
    }
}