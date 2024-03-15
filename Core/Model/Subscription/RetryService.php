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

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Api\Data\SubscriptionInterface;
use UpStreamPay\Core\Api\Data\SubscriptionRetryInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Api\SubscriptionRepositoryInterface;
use UpStreamPay\Core\Api\SubscriptionRetryRepositoryInterface;
use UpStreamPay\Core\Model\Actions\CaptureDuplicateService;
use UpStreamPay\Core\Model\Actions\DuplicateService;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\Subscription;
use UpStreamPay\Core\Model\SubscriptionRetry;

/**
 * Class RetryService
 *
 * @package UpStreamPay\Core\Model\Subscription
 */
class RetryService
{
    /**
     * @param Config $config
     * @param SubscriptionRetryRepositoryInterface $subscriptionRetryRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param SubscriptionRepositoryInterface $subscriptionRepository
     * @param LoggerInterface $logger
     * @param ManagerInterface $eventManager
     * @param DuplicateService $duplicateService
     * @param CartRepositoryInterface $cartRepository
     * @param CaptureDuplicateService $captureDuplicateService
     * @param OrderTransactionsRepositoryInterface $orderTransactionsRepository
     * @param OrderPaymentRepositoryInterface $orderPaymentRepository
     */
    public function __construct(
        private readonly Config $config,
        private readonly SubscriptionRetryRepositoryInterface $subscriptionRetryRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly LoggerInterface $logger,
        private readonly ManagerInterface $eventManager,
        private readonly DuplicateService $duplicateService,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CaptureDuplicateService $captureDuplicateService,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository,
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository
    )
    {
    }

    public function execute(SubscriptionRetryInterface $subscriptionRetry): void
    {
        $numberOfRetries = $subscriptionRetry->getNumberOfRetries() + 1;

        try {
            //First thing we do is update the number of retry, doesn't matter if we actually tried something or not.
            $subscriptionRetry->setNumberOfRetries($numberOfRetries);
            $this->subscriptionRetryRepository->save($subscriptionRetry);
            $subscriptionToRetry = $this->subscriptionRepository->getById($subscriptionRetry->getSubscriptionId());
            $parentSubscriptionToRetry = $this->subscriptionRepository->getParentSubscription($subscriptionToRetry);
            $order = $this->orderRepository->get($subscriptionToRetry->getOrderId());

            if ($subscriptionRetry->canBeRetried()) {
                if ($subscriptionRetry->getRetryType() === OrderTransactions::AUTHORIZE_ACTION) {
                    $quote = $this->cartRepository->get((int)$order->getQuoteId());
                    $this->duplicateService->execute(
                        $quote,
                        $subscriptionRetry->getTransactionId(),
                        $order,
                        $subscriptionToRetry,
                        $parentSubscriptionToRetry,
                        null,
                        true
                    );
                } else {
                    $duplicateAuthorize = $this->orderTransactionsRepository->getByTransactionId(
                        $subscriptionRetry->getTransactionId()
                    );
                    $orderPayment = $this->orderPaymentRepository->getById($duplicateAuthorize->getParentPaymentId());

                    $this->captureDuplicateService->execute(
                        $order,
                        $orderPayment,
                        $subscriptionToRetry,
                        $duplicateAuthorize
                    );
                }
            } else {
                if ($subscriptionToRetry->getSubscriptionStatus() !== Subscription::CANCELED) {
                    $this->cancelSubscription($subscriptionToRetry, $parentSubscriptionToRetry);
                }

                if ($subscriptionRetry->getRetryStatus() !== SubscriptionRetry::FAILURE_STATUS) {
                    $subscriptionRetry->setRetryStatus(SubscriptionRetry::FAILURE_STATUS);
                    $this->subscriptionRetryRepository->save($subscriptionRetry);
                }

                $this->cancelOrder($order);
            }
        } catch (\Throwable $exception) {
            if ($numberOfRetries >= $this->config->getSubscriptionPaymentMaximumPaymentRetry()) {
                $subscriptionRetry->setRetryStatus(SubscriptionRetry::FAILURE_STATUS);
                $this->subscriptionRetryRepository->save($subscriptionRetry);

                if ($subscriptionToRetry && $subscriptionToRetry->getEntityId()) {
                    $this->cancelSubscription($subscriptionToRetry, $parentSubscriptionToRetry);
                } else {
                    $this->logger->critical(
                        sprintf(
                            'Subscription with ID %s must be canceled manually after retry failure.',
                            $subscriptionRetry->getSubscriptionId()
                        )
                    );
                }

                if ($order && $order->getEntityId()) {
                    $this->cancelOrder($order);
                } else {
                    $this->logger->critical(
                        sprintf(
                            'Order linked to subscription ID %s must be canceled manually after retry failure.',
                            $subscriptionRetry->getSubscriptionId()
                        )
                    );
                }
            }

            $this->logger->error(
                "Error while retrying payment of subscription with ID: " . $subscriptionToRetry->getEntityId()
            );
            $this->logger->error($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);
        }
    }

    /**
     * Cancel the subscription & expire the parent subscription.
     *
     * @param SubscriptionInterface $subscriptionToRetry
     * @param SubscriptionInterface $parentSubscription
     *
     * @return void
     * @throws LocalizedException
     */
    private function cancelSubscription(SubscriptionInterface $subscriptionToRetry, SubscriptionInterface $parentSubscription): void
    {
        $subscriptionToRetry
            ->setSubscriptionStatus(Subscription::CANCELED)
            ->setPaymentStatus(Subscription::ERROR)
            ->setNextPaymentDate(null)
        ;
        $this->subscriptionRepository->save($subscriptionToRetry);
        $this->eventManager->dispatch(
            'usp_subscription_canceled',
            [
                'subscription_id' => $subscriptionToRetry->getEntityId(),
                'subscription_identifier' => $subscriptionToRetry->getSubscriptionIdentifier(),
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
