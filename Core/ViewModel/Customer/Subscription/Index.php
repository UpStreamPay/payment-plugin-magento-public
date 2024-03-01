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

namespace UpStreamPay\Core\ViewModel\Customer\Subscription;

use Magento\Customer\Model\Session;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use UpStreamPay\Core\Api\Data\SubscriptionInterface;
use UpStreamPay\Core\Api\SubscriptionRepositoryInterface;
use UpStreamPay\Core\Model\Subscription;
use Magento\Framework\Pricing\Helper\Data;

/**
 * Class Index
 *
 * @package UpStreamPay\Core\ViewModel\Customer\Subscription
 */
class Index implements ArgumentInterface
{
    /**
     * @param Session $customerSession
     * @param SubscriptionRepositoryInterface $subscriptionRepository
     * @param Data $priceHelper
     */
    public function __construct(
        private readonly Session $customerSession,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly Data $priceHelper
    )
    {}

    /**
     * @return array|null
     * @throws LocalizedException
     */
    public function getSubscriptions(): ?array
    {
        $customerId = (int)$this->customerSession->getId();

        return $this->subscriptionRepository->getSubscriptionsToDisplayOnFrontend($customerId);
    }

    /**
     * @param SubscriptionInterface $subscription
     *
     * @return bool
     */
    public function isCurrentSubscription(SubscriptionInterface $subscription): bool
    {
        return ($subscription->getSubscriptionStatus() === Subscription::ENABLED
            && $subscription->getPaymentStatus() === Subscription::PAID);
    }

    /**
     * @param SubscriptionInterface $subscription
     *
     * @return bool
     */
    public function isFutureSubscription(SubscriptionInterface $subscription): bool
    {
        return ($subscription->getSubscriptionStatus() === Subscription::DISABLED
            && $subscription->getPaymentStatus() === Subscription::TO_PAY);
    }

    /**
     * @param SubscriptionInterface $subscription
     * @return string
     */
    public function getFormattedPrice(SubscriptionInterface $subscription): string
    {
        return $this->priceHelper->currency($subscription->getProductPrice(), true, false);
    }

}
