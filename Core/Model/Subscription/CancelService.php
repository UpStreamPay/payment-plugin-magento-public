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

use Magento\Framework\Exception\NoSuchEntityException;
use UpStreamPay\Core\Model\Subscription;
use UpStreamPay\Core\Api\SubscriptionRepositoryInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Throwable;
use Psr\Log\LoggerInterface;

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
        private readonly LoggerInterface $logger
    )
    {
    }

    /**
     * @param int|null $subscriptionId
     * @param CreditmemoInterface|null $creditMemo
     * @return void
     * @throws NoSuchEntityException
     */
    public function execute(int $subscriptionId = null, CreditmemoInterface $creditMemo = null): void
    {
        if ($subscriptionId) {
            try {
                $subscription = $this->subscriptionRepository->getById($subscriptionId);
            } catch (Throwable $exception) {
                $this->logger->critical('Error while trying to save a subscription');
                $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);
                return;
            }
            $associatedOrderId = $subscription->getOrderId();
            if ($associatedOrderId) {
                $this->logger->info('Attempting to cancel a subscription but there is an associated order.');
                $this->logger->info('Subscription id : ' . $subscription->getEntityId());
                $this->logger->info('Asociated order id : ' . $associatedOrderId);
                $this->logger->info('Subscription identifier : ' . $subscription->getSubscriptionIdentifier());
                throw new \Exception;
            } else {
                $subscription
                    ->setSubscriptionStatus(Subscription::CANCELED)
                    ->setPaymentStatus(Subscription::CANCELED);
                $this->subscriptionRepository->save($subscription);
                $this->eventManager->dispatch(
                    'usp_subscription_canceled',
                    ['subscription_id' => $subscription->getEntityId(), 'subscription_identifier' => $subscription->getSubscriptionIdentifier()]
                );
            }
        } elseif ($creditMemo) {
            $creditMemoOrderId = $creditMemo->getOrderId();
            foreach ($creditMemo->getItems() as $creditmemoItem) {
                $productId = $creditmemoItem->getProductId();
                try {
                    $product = $this->productRepository->getById($productId);
                } catch (NoSuchEntityException $exception) {
                    $this->logger->critical(__('No product found with id "%1"', $productId));
                    $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);
                    throw new \Exception;
                }
                $subscription = $this->subscriptionRepository->getByProductSkuAndOrderId($product->getSku(), (int) $creditMemoOrderId);
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
                            'creditmemo_id' => $creditMemo->getEntityId()
                        ]
                    );
                }
            }
        }

    }

}
