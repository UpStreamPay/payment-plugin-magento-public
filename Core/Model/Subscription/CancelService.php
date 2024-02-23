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
use Magento\Sales\Api\Data\CreditmemoInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use UpStreamPay\Core\Api\SubscriptionRepositoryInterface;
use UpStreamPay\Core\Exception\WrongSubscriptionCancelMethodException;
use UpStreamPay\Core\Model\Subscription;

/**
 * Class CancelService
 *
 * @package UpStreamPay\Core\Model\Subscription
 */
class CancelService
{
    /**
     * @param SubscriptionRepositoryInterface $subscriptionRepository
     * @param ProductRepositoryInterface $productRepository
     * @param ManagerInterface $eventManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ManagerInterface $eventManager,
        private readonly LoggerInterface $logger,
    )
    {
    }

    /**
     * This function cancels a subscription based on two things.
     * - a subscription ID is provided, meaning we are trying to cancel a subscription from customer account, admin or
     * through custom event.
     * - a creditmemo is provided, meaning we are refunding an invoice.
     *
     * @param int|null $subscriptionId
     * @param CreditmemoInterface|null $creditMemo
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws WrongSubscriptionCancelMethodException
     */
    public function execute(?int $subscriptionId = null, ?CreditmemoInterface $creditMemo = null): void
    {
        if ($subscriptionId) {
            try {
                $subscription = $this->subscriptionRepository->getById($subscriptionId);
            } catch (Throwable $exception) {
                $this->logger->critical('Error while trying to retrieve subscription with ID ' . $subscriptionId);
                $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);

                throw new NoSuchEntityException();
            }

            $associatedOrderId = $subscription->getOrderId();

            if ($associatedOrderId) {
                $this->logger->critical('Attempting to cancel a subscription but there is an associated order.');
                $this->logger->critical('In order to cancel a subscription linked to an order, use a creditmemo.');
                $this->logger->critical('Subscription id: ' . $subscription->getEntityId());
                $this->logger->critical('Asociated order id: ' . $associatedOrderId);
                $this->logger->critical('Subscription identifier: ' . $subscription->getSubscriptionIdentifier());

                throw new WrongSubscriptionCancelMethodException();
            } else {
                $subscription
                    ->setSubscriptionStatus(Subscription::CANCELED)
                    ->setPaymentStatus(Subscription::CANCELED);
                $this->subscriptionRepository->save($subscription);
                $this->eventManager->dispatch(
                    'usp_subscription_canceled',
                    [
                        'subscription_id' => $subscription->getEntityId(),
                        'subscription_identifier' => $subscription->getSubscriptionIdentifier(),
                    ]
                );
            }
        } elseif ($creditMemo) {
            $orderId = (int)$creditMemo->getOrderId();

            foreach ($creditMemo->getAllItems() as $creditmemoItem) {
                $productId = $creditmemoItem->getProductId();
                $product = $this->productRepository->getById($productId);
                $subscriptions = $this->subscriptionRepository->getAllSubscriptionsToCancel(
                    $product->getSku(),
                    $orderId
                );

                //In case of a configurable product we might end up with no result because of the parent item.
                //Or just because the current creditmemo item does not exist in the subscription table.
                //In both case we need to move on to the next creditmemo item.
                if (null === $subscriptions) {
                    continue;
                }

                foreach ($subscriptions as $subscription) {
                    if ($subscription && $subscription->getEntityId()) {
                        $subscription
                            ->setSubscriptionStatus(Subscription::CANCELED)
                            ->setPaymentStatus(Subscription::CANCELED);
                        $this->subscriptionRepository->save($subscription);
                        $this->eventManager->dispatch(
                            'usp_creditmemo_subscription_canceled',
                            [
                                'subscription_id' => $subscription->getEntityId(),
                                'subscription_identifier' => $subscription->getSubscriptionIdentifier(),
                                'creditmemo_id' => $creditMemo->getEntityId(),
                            ]
                        );
                    }
                }
            }
        }
    }
}
