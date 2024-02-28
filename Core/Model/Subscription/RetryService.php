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
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Payment\Processor;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Api\Data\SubscriptionInterface;
use UpStreamPay\Core\Api\Data\SubscriptionRetryInterface;
use UpStreamPay\Core\Api\SubscriptionRepositoryInterface;
use UpStreamPay\Core\Api\SubscriptionRetryRepositoryInterface;
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
    public function __construct(
        private readonly Config $config,
        private readonly SubscriptionRetryRepositoryInterface $subscriptionRetryRepository,
        private readonly Processor $paymentProcessor,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly InvoiceRepositoryInterface $invoiceRepository,
        private readonly LoggerInterface $logger,
        private readonly ManagerInterface $eventManager
    )
    {
    }

    public function execute(SubscriptionRetryInterface $subscriptionRetry): void
    {
        $subscriptionToRetry = $this->subscriptionRepository->getById($subscriptionRetry->getSubscriptionId());

        if ($subscriptionRetry->canBeRetried()) {
            $order = $this->orderRepository->get($subscriptionToRetry->getOrderId());
            $payment = $order->getPayment();
            $payment->setIsRetry(true);
            $invoice = $this->invoiceRepository->get($subscriptionToRetry->getInvoiceId());

            if ($subscriptionRetry->getRetryType() === OrderTransactions::AUTHORIZE_ACTION) {
                //TODO
                //Call DuplicateService (need UP-132)
                //We need a retrieve a way to know the status of the duplicate, so the the DuplicateService needs
                //to return the proper data.
                //Based on the status, we have actions to do.
                //- success => save the retry status (duplicate service triggers capture or UP-132 creates a service or something to capture after a duplicate).
                //- waiting => save the retry status & do nothing else.
                //- error => save the retry status & check if we reached max number of retry. If so cancel everything (order, subscription etc).
                $status = '';
            } else {
                try {
                    $payment = $this->paymentProcessor->capture($payment, $invoice);

                    //After capture is done trigger pay of the invoice.
                    if ($invoice->getIsPaid()) {
                        $invoice->pay();
                        $status = SubscriptionRetry::SUCCESS_STATUS;
                    } else {
                        if ($payment->getIsRetryInError()) {
                            $status = SubscriptionRetry::ERROR_STATUS;
                        } else {
                            $status = SubscriptionRetry::WAITING_STATUS;
                        }
                    }
                } catch (\Throwable $exception) {
                    //Exception during capture process means we have an error.
                    $status = SubscriptionRetry::ERROR_STATUS;

                    $this->logger->error(
                        "Error while retrying payment of subscription with ID: " . $subscriptionToRetry->getEntityId()
                    );
                    $this->logger->error($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);
                }
            }

            $numberOfRetries = $subscriptionRetry->getNumberOfRetries() + 1;

            if ($numberOfRetries >= $this->config->getSubscriptionPaymentMaximumPaymentRetry()
                && $status === OrderTransactions::ERROR_STATUS) {
                $status = SubscriptionRetry::FAILURE_STATUS;

                $this->cancelSubscription($subscriptionToRetry);
                $this->cancelOrder();
            }

            $subscriptionRetry
                ->setNumberOfRetries($numberOfRetries)
                ->setRetryStatus($status)
            ;

            $this->subscriptionRetryRepository->save($subscriptionRetry);

            $this->eventManager->dispatch(
                'usp_subscription_retry_success',
                [
                    'subscription_id' => $subscriptionToRetry->getEntityId(),
                    'subscription_identifier' => $subscriptionToRetry->getSubscriptionIdentifier(),
                ]
            );
        } else {
            //We should never enter this condition because we trigger cancel & failure after a retry if needed
            //But just in case for safety cancel the retry & subscription if needed.
            if ($subscriptionRetry->getNumberOfRetries() >= $this->config->getSubscriptionPaymentMaximumPaymentRetry()
                && $subscriptionRetry->getRetryStatus() === OrderTransactions::ERROR_STATUS) {
                $this->cancelSubscription($subscriptionToRetry);

                $subscriptionRetry->setRetryStatus(SubscriptionRetry::FAILURE_STATUS);
                $this->subscriptionRetryRepository->save($subscriptionRetry);
            }
        }
    }

    /**
     * @param SubscriptionInterface $subscriptionToRetry
     *
     * @return void
     * @throws LocalizedException
     */
    private function cancelSubscription(SubscriptionInterface $subscriptionToRetry): void
    {
        $subscriptionToRetry
            ->setSubscriptionStatus(Subscription::CANCELED)
            ->setPaymentStatus(Subscription::ERROR);
        $this->subscriptionRepository->save($subscriptionToRetry);
        $this->eventManager->dispatch(
            'usp_subscription_canceled',
            [
                'subscription_id' => $subscriptionToRetry->getEntityId(),
                'subscription_identifier' => $subscriptionToRetry->getSubscriptionIdentifier(),
            ]
        );
    }
}
