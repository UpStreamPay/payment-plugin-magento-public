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

namespace UpStreamPay\Core\Model\Actions;

use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment\Processor;
use Magento\Sales\Model\Order\StatusResolver;
use Psr\Log\LoggerInterface;
use UpStreamPay\Client\Model\Client\Client;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Api\SubscriptionRepositoryInterface;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Config\Source\Debug;
use UpStreamPay\Core\Model\OrderPayment;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\Session\Order\OrderService;
use UpStreamPay\Core\Model\Session\PurseSessionDataManager;
use UpStreamPay\Core\Model\Subscription;
use UpStreamPay\Core\Model\Subscription\Retry\HandleRetry;
use UpStreamPay\Core\Model\SubscriptionRetry;
use UpStreamPay\Core\Model\Synchronize\SynchronizeUpStreamPayPaymentData;
use UpStreamPay\Core\Observer\SetOrderSentToPurseObserver;

/**
 * Class DuplicateService
 *
 * @package UpStreamPay\Core\Model\Actions
 */
class DuplicateService
{
    /**
     * @param OrderTransactionsRepositoryInterface $orderTransactionsRepository
     * @param Client $client
     * @param OrderService $orderService
     * @param EventManager $eventManager
     * @param Processor $paymentProcessor
     * @param CartRepositoryInterface $cartRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderTransactions $orderTransactions
     * @param OrderPayment $orderPayment
     * @param SynchronizeUpStreamPayPaymentData $synchronizeUpStreamPayPaymentData
     * @param SubscriptionRepositoryInterface $subscriptionRepository
     * @param Config $config
     * @param LoggerInterface $logger
     * @param StatusResolver $statusResolver
     * @param CaptureDuplicateService $captureDuplicate
     * @param HandleRetry $handleRetry
     */
    public function __construct(
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository,
        private readonly Client $client,
        private readonly OrderService $orderService,
        private readonly EventManager $eventManager,
        private readonly Processor $paymentProcessor,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderTransactions $orderTransactions,
        private readonly OrderPayment $orderPayment,
        private readonly SynchronizeUpStreamPayPaymentData $synchronizeUpStreamPayPaymentData,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly StatusResolver $statusResolver,
        private readonly CaptureDuplicateService $captureDuplicate,
        private readonly HandleRetry $handleRetry
    )
    {
    }

    /**
     * Duplicate the authorize transaction & capture it.
     *
     * @param CartInterface|Quote $quote
     * @param string $transactionId
     * @param OrderInterface|Order $order
     * @param Subscription $subscription
     * @param Subscription $parentSubscription
     * @param null|OrderTransactionsInterface $duplicateAuthorize
     *
     * @return void
     * @throws LocalizedException
     */
    public function execute(
        CartInterface|Quote $quote,
        string $transactionId,
        OrderInterface|Order $order,
        Subscription $subscription,
        Subscription $parentSubscription,
        ?OrderTransactionsInterface $duplicateAuthorize = null
    ): void
    {
        $body = $this->orderService->execute($quote, true);
        $orderId = (int)$order->getEntityId();
        $quoteId = (int)$quote->getEntityId();
        $payment = $order->getPayment();

        try {
            //If $duplicateAuthorize is null it means we are not coming from a waiting notification.
            if ($duplicateAuthorize === null) {
                $response = $this->client->duplicate($transactionId, $body);
                $duplicateAuthorize = $this->orderTransactions->createTransactionFromResponse(
                    $response,
                    $orderId,
                    $quoteId,
                    null,
                    null,
                    $subscription->getEntityId()
                );
            }
        } catch (\Throwable $exception) {
            $state = Order::STATE_PENDING_PAYMENT;
            $this->handleOrderStatus(
                $order,
                $state,
                sprintf(
                    'Error while trying to call the capture API & save the subscription for order %s: %s',
                    $order->getIncrementId(),
                    $exception->getMessage()
                )
            );

            //In case of an error here, we can create a retry.
            $this->handleRetry->execute(
                $subscription,
                OrderTransactions::AUTHORIZE_ACTION,
                SubscriptionRetry::ERROR_STATUS,
                $transactionId,
                $order
            );

            return;
        }

        try {
            if ($duplicateAuthorize->getStatus() === OrderTransactions::SUCCESS_STATUS) {
                $orderPayment = $this->orderPayment->createPaymentFromTransaction(
                    $duplicateAuthorize,
                    (int)$payment->getEntityId(),
                    $this->synchronizeUpStreamPayPaymentData->getPaymentMethodTypeFromTransaction($duplicateAuthorize)
                );

                $duplicateAuthorize->setParentPaymentId($orderPayment->getEntityId());
                $this->orderTransactionsRepository->save($duplicateAuthorize);

                $this->eventManager->dispatch(SetOrderSentToPurseObserver::EVENT_NAME, [
                    'order' => $order,
                ]);

                $quote
                    ->getPayment()
                    ->setData(PurseSessionDataManager::PAYMENT_PURSE_SESSION_ID, $duplicateAuthorize->getSessionId())
                    ->setData(
                        PurseSessionDataManager::QUOTE_PAYMENT_PURSE_SESSION_AMOUNT_KEY,
                        $duplicateAuthorize->getAmount()
                    );

                $this->cartRepository->save($quote);

                //The subscription we are trying to renew is enabled (but not paid !!!).
                $subscription
                    ->setSubscriptionStatus(Subscription::ENABLED);
                $this->subscriptionRepository->save($subscription);

                //The parent subscription is the previous subscription, it's not expired because the new one is active.
                $parentSubscription->setSubscriptionStatus(Subscription::EXPIRED);
                $this->subscriptionRepository->save($parentSubscription);

                $this->paymentProcessor->order($payment, $order->getBaseTotalDue());
                $this->orderRepository->save($order);

                //In case there was an existing retry on this action, the service will handle update of the retry
                //to flag as a success.
                $this->handleRetry->execute(
                    $subscription,
                    OrderTransactions::AUTHORIZE_ACTION,
                    SubscriptionRetry::SUCCESS_STATUS,
                    $transactionId,
                    $order
                );

                //The duplication is a success and there is no error, now we can try to capture & invoice the order.
                $this->captureDuplicate->execute($order, $orderPayment, $subscription, $duplicateAuthorize);
            } else {
                if ($duplicateAuthorize->getStatus() === OrderTransactions::ERROR_STATUS) {
                    //Add a retry in error.
                    $this->handleRetry->execute(
                        $subscription,
                        OrderTransactions::AUTHORIZE_ACTION,
                        SubscriptionRetry::ERROR_STATUS,
                        $transactionId,
                        $order
                    );

                    $orderComment = sprintf(
                        'Duplicate authorize with transaction ID %s is in error, subscription can\'t be renewed yet.',
                        $duplicateAuthorize->getTransactionId()
                    );

                    $debugMessage = sprintf(
                        "Duplicate authorize %s resulted in an error for order %s.",
                        $duplicateAuthorize->getTransactionId(),
                        $order->getIncrementId()
                    );
                } else {
                    //Add a retry in waiting. When we have the notification we will update it to error or success.
                    //We don't actually create a new waiting retry, this is in case of update only.
                    $this->handleRetry->execute(
                        $subscription,
                        OrderTransactions::AUTHORIZE_ACTION,
                        SubscriptionRetry::WAITING_STATUS,
                        $transactionId,
                        $order
                    );

                    $orderComment = sprintf(
                        'Duplicate authorize with transaction ID %s is in waiting.',
                        $duplicateAuthorize->getTransactionId()
                    );

                    $debugMessage = sprintf(
                        "Duplicate authorize %s is in waiting while trying to pay order %s.",
                        $duplicateAuthorize->getTransactionId(),
                        $order->getIncrementId()
                    );
                }

                //In case of an error or waiting, the order is set to a payment review state.
                $this->handleOrderStatus($order, Order::STATE_PAYMENT_REVIEW, $orderComment);
                $this->log($debugMessage, $orderComment);
            }
        } catch (\Throwable $exception) {
            //In case of an error not linked to the API or authorize status, we have no choice but to cancel.
            $this->logger->error(
                sprintf(
                    'There was an error after authorize duplicate %s',
                    $duplicateAuthorize->getTransactionId()
                )
            );
            $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);
            $subscription
                ->setSubscriptionStatus(Subscription::CANCELED)
                ->setPaymentStatus(Subscription::CANCELED)
                ->setNextPaymentDate(null)
            ;
            $this->subscriptionRepository->save($subscription);

            $parentSubscription->setSubscriptionStatus(Subscription::EXPIRED);
            $this->subscriptionRepository->save($parentSubscription);

            $renewOrder->cancel();
            $this->orderRepository->save($renewOrder);
        }
    }

    /**
     * @param string $debugMessage
     * @param string $orderMessage
     *
     * @return void
     */
    private function log(string $debugMessage, string $orderMessage): void
    {
        if ($this->config->getDebugMode() === Debug::DEBUG_VALUE) {
            $this->logger->debug($debugMessage);
            $this->logger->debug($orderMessage);
        }
    }

    /**
     * If we have an error or waiting, update the order properly (state, status, comment).
     *
     * @param Order $order
     * @param string $state
     * @param string $orderComment
     *
     * @return void
     */
    private function handleOrderStatus(Order $order, string $state, string $orderComment): void
    {
        $order->setState($state);
        $order->addCommentToStatusHistory(
            $orderComment,
            $this->statusResolver->getOrderStatusByState($order, $state)
        );

        $this->orderRepository->save($order);
    }
}
