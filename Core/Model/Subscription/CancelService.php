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

use UpStreamPay\Core\Model\Subscription;
use UpStreamPay\Core\Model\SubscriptionRepository;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Model\Order\CreditmemoRepository;
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
     * @param SubscriptionRepository $subscriptionRepository
     * @param CreditmemoInterface $creditmemo
     * @param CreditmemoRepository $creditmemoRepository
     * @param ManagerInterface $eventManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly CreditmemoInterface $creditmemo,
        private readonly CreditmemoRepository $creditmemoRepository,
        private readonly ManagerInterface $eventManager,
        private readonly LoggerInterface $logger
    )
    {
    }

    /**
     * @param int $subscriptionId
     * @param int|null $creditMemoId
     * @return void
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function execute(int $subscriptionId = null, int $creditMemoId = null): void
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
        } elseif ($creditMemoId) {
            $creditMemo = $this->creditmemoRepository->get($creditMemoId);
            $creditMemoOrderId = $creditMemo->getOrderId();
            foreach ($creditMemo->getItems() as $creditmemoItem) {

            }
        }

    }

}
