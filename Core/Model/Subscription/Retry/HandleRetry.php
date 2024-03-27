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

namespace UpStreamPay\Core\Model\Subscription\Retry;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Api\Data\SubscriptionInterface;
use UpStreamPay\Core\Api\Data\SubscriptionRetryInterface;
use UpStreamPay\Core\Api\SubscriptionRepositoryInterface;
use UpStreamPay\Core\Api\SubscriptionRetryRepositoryInterface;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Subscription;
use UpStreamPay\Core\Model\SubscriptionRetry;
use UpStreamPay\Core\Model\SubscriptionRetryFactory;

/**
 * Class HandleRetry
 *
 * @package UpStreamPay\Core\Model\Subscription\Retry
 */
class HandleRetry
{
    /**
     * @param SubscriptionRepositoryInterface $subscriptionRepository
     * @param SubscriptionRetryRepositoryInterface $subscriptionRetryRepository
     * @param SubscriptionRetryFactory $subscriptionRetryFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ManagerInterface $eventManager
     * @param LoggerInterface $logger
     * @param Config $config
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly SubscriptionRetryRepositoryInterface $subscriptionRetryRepository,
        private readonly SubscriptionRetryFactory $subscriptionRetryFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly ManagerInterface $eventManager,
        private readonly LoggerInterface $logger,
        private readonly Config $config,
        private readonly OrderRepositoryInterface $orderRepository
    )
    {
    }

    /**
     * Handle the retry (update or create).
     *
     * @param SubscriptionInterface $subscription
     * @param string $action
     * @param string $status
     * @param string $transactionId
     * @param OrderInterface $order
     *
     * @return void
     * @throws LocalizedException
     */
    public function execute(
        SubscriptionInterface $subscription,
        string $action,
        string $status,
        string $transactionId,
        OrderInterface $order
    ): void
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(SubscriptionRetryInterface::SUBSCRIPTION_ID, $subscription->getEntityId())
                ->addFilter(SubscriptionRetryInterface::RETRY_TYPE, $action)
                ->create();

            //We can only have one retry based on the filter we use above, can't have duplicate.
            $subscriptionRetry = $this->subscriptionRetryRepository->getList($searchCriteria)->getItems();

            if (count($subscriptionRetry) > 0) {
                foreach ($subscriptionRetry as $retry) {
                    if ($this->canUpdateRetry($retry)) {
                        $this->updateRetry($status, $retry, $subscription, $order);
                    }
                }
            } elseif ($status === SubscriptionRetry::ERROR_STATUS) {
                $this->createRetry($subscription, $action, $transactionId);
            }
        } catch (\Throwable $exception) {
            $this->cancelSubscription($subscription);
            $this->cancelOrder($order);

            $this->logger->critical(
                sprintf(
                    'Erreur while trying to retry %s for subscription with ID %s for transaction with %s',
                    $action,
                    $subscription->getEntityId(),
                    $transactionId
                )
            );

            $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);
        }
    }

    /**
     * In case we don't have a retry
     *
     * @param SubscriptionInterface $subscription
     * @param string $action
     * @param string $transactionId
     *
     * @return void
     * @throws LocalizedException
     */
    private function createRetry(SubscriptionInterface $subscription, string $action, string $transactionId): void
    {
        $subscriptionRetry = $this->subscriptionRetryFactory->create();
        $subscriptionRetry
            ->setSubscriptionId($subscription->getEntityId())
            ->setNumberOfRetries(0)
            ->setRetryStatus(SubscriptionRetry::ERROR_STATUS)
            ->setRetryType($action)
            ->setTransactionId($transactionId)
        ;

        $this->subscriptionRetryRepository->save($subscriptionRetry);

        $subscription->setPaymentStatus(Subscription::RETRY_PAYMENT);
        $this->subscriptionRepository->save($subscription);
    }

    /**
     * As of now the only action is to update the retry status & check if we have reach maximum number of retry.
     * The increment of retries is already done before triggering the retry process.
     *
     * @param string $status
     * @param SubscriptionRetryInterface $subscriptionRetry
     * @param SubscriptionInterface $subscription
     * @param OrderInterface $order
     *
     * @return void
     * @throws LocalizedException
     */
    private function updateRetry(
        string $status,
        SubscriptionRetryInterface $subscriptionRetry,
        SubscriptionInterface $subscription,
        OrderInterface $order
    ): void
    {
        if ($subscriptionRetry->getNumberOfRetries() === $this->config->getSubscriptionPaymentMaximumPaymentRetry()) {
            $subscriptionRetry->setRetryStatus(SubscriptionRetry::FAILURE_STATUS);

            $this->cancelSubscription($subscription);
            $this->cancelOrder($order);
        } else {
            $subscriptionRetry->setRetryStatus($status);
        }

        $this->subscriptionRetryRepository->save($subscriptionRetry);
    }

    /**
     * We only want to update a retry in waiting or error.
     *
     * @param SubscriptionRetryInterface $retry
     *
     * @return bool
     */
    private function canUpdateRetry(SubscriptionRetryInterface $retry): bool
    {
        return $retry->getRetryStatus() === SubscriptionRetry::ERROR_STATUS
            || $retry->getRetryStatus() === SubscriptionRetry::WAITING_STATUS;
    }

    /**
     * In case we have reach the maximum number of retry, cancel the associated subscription.
     *
     * @param $subscription
     *
     * @return void
     * @throws LocalizedException
     */
    private function cancelSubscription($subscription): void
    {
        $parentSubscription = $this->subscriptionRepository->getParentSubscription($subscription);

        $subscription
            ->setSubscriptionStatus(Subscription::CANCELED)
            ->setPaymentStatus(Subscription::ERROR)
            ->setNextPaymentDate(null)
        ;
        $this->subscriptionRepository->save($subscription);
        $this->eventManager->dispatch(
            'usp_subscription_canceled',
            [
                'subscription_id' => $subscription->getEntityId(),
                'subscription_identifier' => $subscription->getSubscriptionIdentifier(),
            ]
        );

        $parentSubscription->setSubscriptionStatus(Subscription::EXPIRED);
        $this->subscriptionRepository->save($parentSubscription);
        $this->eventManager->dispatch(
            'usp_subscription_canceled',
            [
                'subscription_id' => $parentSubscription->getEntityId(),
                'subscription_identifier' => $parentSubscription->getSubscriptionIdentifier(),
            ]
        );
    }

    /**
     * Cancel the order if possible, else deny the payment.
     *
     * @param OrderInterface $order
     *
     * @return void
     * @throws LocalizedException
     */
    private function cancelOrder(OrderInterface $order): void
    {
        if ($order->canCancel()) {
            $order->cancel();
        } else {
            $order->getPayment()->deny();
        }

        $this->orderRepository->save($order);
    }
}
