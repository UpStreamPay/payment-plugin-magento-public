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

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\CreditmemoManagementInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\CreditmemoFactory;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\StatusResolver;
use Magento\Sales\Model\Service\InvoiceService;
use Psr\Log\LoggerInterface;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Api\Data\OrderPaymentInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\Data\SubscriptionInterface;
use UpStreamPay\Core\Api\SubscriptionRepositoryInterface;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Config\Source\Debug;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\Subscription;
use UpStreamPay\Core\Model\Subscription\Retry\HandleRetry;
use UpStreamPay\Core\Model\SubscriptionRetry;

/**
 * Class CaptureDuplicateService
 *
 * @package UpStreamPay\Core\Model\Actions
 */
class CaptureDuplicateService
{
    /**
     * @param LoggerInterface $logger
     * @param ClientInterface $client
     * @param OrderTransactionsInterface $orderTransactions
     * @param InvoiceService $invoiceService
     * @param InvoiceRepositoryInterface $invoiceRepository
     * @param OrderSender $orderSender
     * @param InvoiceSender $invoiceSender
     * @param OrderRepositoryInterface $orderRepository
     * @param CreditmemoFactory $creditmemoFactory
     * @param CreditmemoManagementInterface $creditmemoManagement
     * @param StatusResolver $statusResolver
     * @param Config $config
     * @param SubscriptionRepositoryInterface $subscriptionRepository
     * @param HandleRetry $handleRetry
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ClientInterface $client,
        private readonly OrderTransactionsInterface $orderTransactions,
        private readonly InvoiceService $invoiceService,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly OrderSender $orderSender,
        private readonly InvoiceSender $invoiceSender,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly CreditmemoFactory $creditmemoFactory,
        private readonly CreditmemoManagementInterface $creditmemoManagement,
        private readonly StatusResolver $statusResolver,
        private readonly Config $config,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly HandleRetry $handleRetry
    )
    {
    }

    /**
     * @param OrderInterface|Order $order
     * @param OrderPaymentInterface $orderPayment
     * @param SubscriptionInterface $subscription
     * @param null|OrderTransactionsInterface $duplicateAuthorize
     * @param null|OrderTransactionsInterface $capture
     *
     * @return void
     * @throws LocalizedException
     */
    public function execute(
        OrderInterface|Order $order,
        OrderPaymentInterface $orderPayment,
        SubscriptionInterface $subscription,
        ?OrderTransactionsInterface $duplicateAuthorize = null,
        ?OrderTransactionsInterface $capture = null,
    ): void
    {
        $invoice = null;

        //If the capture is null it means we come from the renewal process, else we handle a notification.
        if ($capture === null) {
            $body = [
                'order' => [
                    'amount' => $order->getBaseGrandTotal(),
                    'currency_code' => $order->getGlobalCurrencyCode(),
                ],
                'amount' => $order->getBaseGrandTotal(),
            ];

            try {
                $response = $this->client->capture($duplicateAuthorize->getTransactionId(), $body);
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

                $this->handleRetry->execute(
                    $subscription,
                    OrderTransactions::CAPTURE_ACTION,
                    SubscriptionRetry::ERROR_STATUS,
                    $duplicateAuthorize->getTransactionId(),
                    $order
                );

                return;
            }

            try {
                $capture = $this->orderTransactions->createTransactionFromResponse(
                    $response,
                    (int)$order->getEntityId(),
                    (int)$order->getQuoteId(),
                    $orderPayment->getEntityId(),
                    null,
                    $subscription->getEntityId()
                );
            } catch (\Throwable $exception) {
                //This should never happen, but just in case.
                $this->logger->critical(
                    sprintf(
                        'Error while saving capture transaction to database. Refund transaction %s in Purse BO.',
                        $response['id']
                    )
                );

                $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);
                $this->cancelOrder($subscription, $order);

                return;
            }
        }

        try {
            if ($capture->getStatus() === OrderTransactions::SUCCESS_STATUS) {
                $invoice = $this->invoiceService->prepareInvoice($order);
                $invoice->setRequestedCaptureCase('online');
                $invoice->register();

                $invoice->getOrder()->setIsInProcess(true);
                $this->invoiceRepository->save($invoice);
                $this->orderSender->send($order);
                $this->invoiceSender->send($invoice);
            } else {
                if ($capture->getStatus() === OrderTransactions::ERROR_STATUS) {
                    $this->handleRetry->execute(
                        $subscription,
                        OrderTransactions::CAPTURE_ACTION,
                        SubscriptionRetry::ERROR_STATUS,
                        $capture->getParentTransactionId(),
                        $order
                    );

                    $orderComment = sprintf(
                        'Capture with transaction ID %s is in error, subscription can\'t be renewed yet.',
                        $capture->getTransactionId()
                    );

                    $debugMessage = sprintf(
                        "Duplicate authorize %s resulted in an error while trying to capture for order %s.",
                        $capture->getParentTransactionId(),
                        $order->getIncrementId()
                    );
                } else {
                    //Add a retry in waiting. When we have the notification we will update it error or success.
                    //We don't actually create a new waiting retry, this is in case of update only.
                    $this->handleRetry->execute(
                        $subscription,
                        OrderTransactions::CAPTURE_ACTION,
                        SubscriptionRetry::WAITING_STATUS,
                        $capture->getParentTransactionId(),
                        $order
                    );

                    $orderComment = sprintf(
                        'Capture with transaction ID %s is in waiting.',
                        $capture->getTransactionId()
                    );

                    $debugMessage = sprintf(
                        "Capture %s is in waiting while trying to pay order %s.",
                        $capture->getTransactionId(),
                        $order->getIncrementId()
                    );
                }

                $this->handleOrderStatus($order, Order::STATE_PAYMENT_REVIEW, $orderComment);
                $this->log($debugMessage, $orderComment);
            }
        } catch (\Throwable $exception) {
            //In case of an error while trying to update the order or subscription, we have no choice but to cancel.
            $this->logger->error(
                sprintf(
                    'There was an error while trying to update quote & order after authorize duplicate %s',
                    $capture->getParentTransactionId()
                )
            );
            $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);
            $order->addCommentToStatusHistory($exception->getMessage());

            if ($invoice === null) {
                $this->cancelOrder($subscription, $order);
            } else {
                //Refund the invoice.
                $creditmemo = $this->creditmemoFactory->createByInvoice($invoice, $invoice->getData());

                foreach ($creditmemo->getAllItems() as $creditmemoItem) {
                    $creditmemoItem->setBackToStock(true);
                }

                $this->creditmemoManagement->refund($creditmemo);
            }
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
        $order->setState($state);;
        $order->addCommentToStatusHistory(
            $orderComment,
            $this->statusResolver->getOrderStatusByState($order, $state)
        );

        $this->orderRepository->save($order);
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
     * Cancel the order & the subscription.
     *
     * @param SubscriptionInterface $subscription
     * @param OrderInterface $order
     *
     * @return void
     * @throws LocalizedException
     */
    private function cancelOrder(SubscriptionInterface $subscription, OrderInterface $order): void
    {
        $subscription
            ->setSubscriptionStatus(Subscription::CANCELED)
            ->setPaymentStatus(Subscription::CANCELED)
            ->setNextPaymentDate(null)
        ;

        $this->subscriptionRepository->save($subscription);

        $parentSubscription = $this->subscriptionRepository->getParentSubscription($subscription);
        $parentSubscription->setSubscriptionStatus(Subscription::EXPIRED);
        $this->subscriptionRepository->save($parentSubscription);

        if ($order->canCancel()) {
            $order->cancel();
        } else {
            $order->getPayment()->deny();
        }
        $this->orderRepository->save($order);
    }
}
