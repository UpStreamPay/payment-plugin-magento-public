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
use Magento\Framework\Math\FloatComparator;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Api\Data\SubscriptionInterface;
use UpStreamPay\Core\Model\Actions\DuplicateService;
use UpStreamPay\Core\Model\Session\PurseSessionDataManager;
use UpStreamPay\Core\Model\Subscription\Renew\CartManagement;
use UpStreamPay\Core\Model\SubscriptionRepository;

/**
 * Class RenewSubscriptionService
 *
 * @package UpStreamPay\Core\Model\Subscription
 */
class RenewSubscriptionService
{
    //Used to reference the original increment id of the original order that purchased the subscription.
    public const ORIGINAL_INCREMENT_ID = 'original_increment_id';

    /**
     * @param SubscriptionRepository $subscriptionRepository
     * @param ProductRepository $productRepository
     * @param EventManager $eventManager
     * @param CartManagement $cartManagementRenew
     * @param OrderRepositoryInterface $orderRepository
     * @param LoggerInterface $logger
     * @param DuplicateService $duplicateService
     * @param FloatComparator $floatComparator
     */
    public function __construct
    (
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly ProductRepository $productRepository,
        private readonly EventManager $eventManager,
        private readonly CartManagement $cartManagementRenew,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly LoggerInterface $logger,
        private readonly DuplicateService $duplicateService,
        private readonly FloatComparator $floatComparator,
    )
    {
    }

    /**
     * Renew the given subscription.
     *
     * - First a new quote is created.
     * - Then a new order is created.
     * - The duplication of the authorize transaction & capture process is then called.
     *
     * @param SubscriptionInterface $subscription
     *
     * @return void
     * @throws LocalizedException
     */
    public function execute(SubscriptionInterface $subscription): void
    {
        try {
            $parentSubscription = $this->subscriptionRepository->getParentSubscription($subscription);
            $order = $this->orderRepository->get($parentSubscription->getOrderId());
            $product = $this->productRepository->get($subscription->getProductSku());
        } catch (\Throwable $exception) {
            //If we can't even retrieve the product or update the subscription price, then no need to go even further.
            $this->logger->error(
                sprintf(
                    'Error while trying to renew the subscription %s before even creating the order, cancelling.',
                    $subscription->getEntityId()
                )
            );
            $this->cancelSubscription($subscription, $exception);

            return;
        }

        try {
            $quote = $this->cartManagementRenew->createQuote($subscription->getProductSku(), (int)$order->getQuoteId());

            foreach ($quote->getAllVisibleItems() as $item) {
                if ($item->getSku() === $subscription->getProductSku()) {
                    //We always use the current product price, so if it's different, we update the subscription price & this
                    //event can be used by the merchant to trigger some custom logic abt price change (send customer email...).
                    if (!$this->floatComparator->equal($subscription->getProductPrice(), (float)$item->getBaseRowTotalInclTax())) {
                        $subscription->setProductPrice((float)$item->getBaseRowTotalInclTax());
                        $this->subscriptionRepository->save($subscription);

                        $this->eventManager->dispatch('subscription_usp_price_update', [
                            'newProductPrice' => (float)$item->getBaseRowTotalInclTax(),
                            'subscription' => $subscription,
                            'sku' => $product->getSku(),
                            'qty' => $subscription->getQty()
                        ]);
                    }
                }
            }
        } catch (\Throwable $exception) {
            //In case of error while creating the quote, cancel the subscription to renew & don't process any further.
            $this->cancelSubscription($subscription, $exception);
            $this->logger->critical(
                'The quote could not be created, no payment has been made & the subscription has been canceled.'
            );

            return;
        }

        try {
            $renewOrder = $this->cartManagementRenew->submitQuote($quote);
            //Very important to set the original increment id to be able to create the subscription identifier.
            //In case this is the first renew, $order is the original order => the original increment id is null,
            //so we have to use the default increment id.
            $renewOrder->setData(
                self::ORIGINAL_INCREMENT_ID,
                $order->getData(self::ORIGINAL_INCREMENT_ID) ?? $order->getIncrementId()
            );

            $renewOrder->getPayment()->setData(
                PurseSessionDataManager::PAYMENT_PURSE_SESSION_ID,
                $order->getPayment()->getData(PurseSessionDataManager::PAYMENT_PURSE_SESSION_ID)
            );
            $this->orderRepository->save($renewOrder);

            //Once we have the renewal order, the subscription to renew is linked to it.
            $subscription->setOrderId((int)$renewOrder->getEntityId());
            $this->subscriptionRepository->save($subscription);
        } catch (\Throwable $exception) {
            //In case of error while creating the order, cancel the subscription to renew & don't process any further.
            $this->cancelSubscription($subscription, $exception);

            $this->logger->critical(
                'The order could not be created, no payment has been made & the subscription has been canceled.'
            );

            return;
        }

        $this->duplicateService->execute(
            $quote,
            $subscription->getOriginalTransactionId(),
            $renewOrder,
            $subscription,
            $parentSubscription
        );
    }

    /**
     * @param SubscriptionInterface $subscription
     * @param \Throwable $exception
     *
     * @return void
     */
    private function cancelSubscription(SubscriptionInterface $subscription, \Throwable $exception): void
    {
        $this->eventManager->dispatch(
            'cancel_purse_subscription',
            [
                'subscriptionId', $subscription->getEntityId()
            ]
        );

        $this->logger->error($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);
    }
}
