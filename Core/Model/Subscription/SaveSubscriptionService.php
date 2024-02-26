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

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Item;
use Psr\Log\LoggerInterface;
use Throwable;
use UpStreamPay\Core\Api\Data\SubscriptionInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Api\SubscriptionRepositoryInterface;
use UpStreamPay\Core\Exception\CreateSubscriptionException;
use UpStreamPay\Core\Exception\NoTransactionsException;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Subscription;
use UpStreamPay\Core\Model\SubscriptionFactory;

/**
 * Class SaveSubscriptionService
 *
 * @package UpStreamPay\Core\Model\Subscription
 */
class SaveSubscriptionService
{

    /**
     * @param SubscriptionFactory $subscriptionFactory
     * @param SubscriptionRepositoryInterface $subscriptionRepository
     * @param ProductRepositoryInterface $productRepository
     * @param ManagerInterface $eventManager
     * @param Config $config
     * @param LoggerInterface $logger
     * @param OrderTransactionsRepositoryInterface $orderTransactionsRepository
     */
    public function __construct(
        private readonly SubscriptionFactory $subscriptionFactory,
        private readonly SubscriptionRepositoryInterface $subscriptionRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ManagerInterface $eventManager,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository
    )
    {
    }

    /**
     * @param OrderInterface $order
     * @param Invoice $invoice
     *
     * @return void
     * @throws CreateSubscriptionException
     * @throws NoSuchEntityException
     * @throws NoTransactionsException
     */
    public function execute(OrderInterface $order, Invoice $invoice): void
    {
        try {
            $transactionId = $this->orderTransactionsRepository->getByInvoiceIdAndPrimaryMethod((int)$invoice->getEntityId());
        } catch (Throwable $exception) {
            $this->logger->critical('Error while trying to load a transaction for invoice with ID: ' . $invoice->getEntityId());
            $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);

            throw new NoTransactionsException();
        }

        $subscriptionEligibleAttrCode = $this->config->getSubscriptionPaymentProductSubscriptionAttributeCode();
        $subscriptionDurationAttrCode = $this->config->getSubscriptionPaymentProductSubscriptionDurationAttributeCode();
        //Use original_increment_id if set, otherwise use increment_id.
        $incrementId = $order->getData(RenewSubscriptionService::ORIGINAL_INCREMENT_ID) ?? $order->getIncrementId();

        /** @var Item $invoiceItem */
        foreach ($invoice->getItems() as $invoiceItem) {
            $product = $this->productRepository->getById($invoiceItem->getProductId());
            $productSubDuration = $product->getData($subscriptionDurationAttrCode);

            //In case product is not a subscription, no need to process this item.
            if (!$product->getData($subscriptionEligibleAttrCode) && !isset($productSubDuration)) {
                continue;
            }

            /* check if there already is a subscription with that order and product */
            try {
                $existingSubscription = $this->subscriptionRepository->getBySubscriptionIdentifier(
                    $product->getSku() . '_' . $incrementId
                );
            } catch (Throwable $exception) {
                $this->logger->critical(
                    'Error while trying to load an existing subscription for order: ' . $order->getIncrementId()
                );
                $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);

                throw new NoSuchEntityException(__($exception->getMessage()));
            }

            //At this point if we have no subscription found it means that this is the original order, not a renewal.
            if (!$existingSubscription || !$existingSubscription->getEntityId()) {
                try {
                    $subscription = $this->createAndSaveSubscription(
                        $incrementId,
                        (int)$order->getEntityId(),
                        $product,
                        $transactionId,
                        $subscriptionDurationAttrCode
                    );
                    $futureSubscription = $this->createAndSaveSubscription(
                        $incrementId,
                        (int)$order->getEntityId(),
                        $product,
                        $transactionId,
                        $subscriptionDurationAttrCode,
                        true
                    );
                    $this->eventManager->dispatch(
                        'new_usp_subscription_saved',
                        [
                            'subscription' => $subscription,
                            'future_subscription' => $futureSubscription,
                        ]
                    );
                } catch (Throwable $exception) {
                    $this->logger->critical(
                        'Error while trying to the create subscriptions for order ' . $incrementId
                    );
                    $this->logger->critical(
                        $exception->getMessage(),
                        [
                            'exception' => $exception->getTraceAsString(),
                        ]
                    );

                    throw new CreateSubscriptionException($exception->getMessage());
                }
            } else {
                //TODO we have at least one subscription found.
                //This means we just paid an invoice and we already have at least 1 subscription line for this invoice item.
                //We are renewing a subscription, meaning we have to create the future subscription.
                //- current active subscription becomes expired bcs we are trying to renew the subscription (if not set inactive previously).
                //- the existing future subscription (the one that we are trying to renew) becomes the current subscription with proper status & payment status.
                //- the future subscription (next subscription to pay) does not exist yet & must be created.
            }
        }
    }

    /**
     * @param string $incrementId
     * @param int $orderId
     * @param ProductInterface $product
     * @param string $transactionId
     * @param string $subscriptionDurationAttrCode
     * @param bool $future
     *
     * @return SubscriptionInterface
     * @throws LocalizedException
     */
    public function createAndSaveSubscription(
        string $incrementId,
        int $orderId,
        ProductInterface $product,
        string $transactionId,
        string $subscriptionDurationAttrCode,
        bool $future = false
    ): SubscriptionInterface
    {
        $subscription = $this->subscriptionFactory->create()
            ->setSubscriptionIdentifier($product->getSku() . '_' . $incrementId)
            ->setProductPrice((float)$product->getPrice())
            ->setProductName($product->getName())
            ->setProductSku($product->getSku())
            ->setOriginalTransactionId($transactionId);

        $customerId = $order->getCustomerId();
        if ($customerId) {
            $subscription->setCustomerId((int)$customerId);
        }

        /* set first subscription dates */
        $startDate = date('Y-m-d', time());
        $endDate = date(
            'Y-m-d',
            strtotime($startDate . ' + ' . $product->getData($subscriptionDurationAttrCode) . ' days')
        );

        if (!$future) {
            $subscription
                ->setSubscriptionStatus(Subscription::ENABLED)
                ->setPaymentStatus(Subscription::PAID)
                ->setStartDate($startDate)
                ->setEndDate($endDate)
                ->setOrderId($orderId);
        } else {
            $futureStartDate = date('Y-m-d', strtotime($endDate . ' + 1 days'));
            $futureEndDate = date(
                'Y-m-d',
                strtotime($futureStartDate . ' + ' . $product->getData($subscriptionDurationAttrCode) . ' days')
            );

            $subscription
                ->setSubscriptionStatus(Subscription::DISABLED)
                ->setPaymentStatus(Subscription::TO_PAY)
                ->setStartDate($futureStartDate)
                ->setEndDate($futureEndDate)
                ->setNextPaymentDate($futureEndDate);
        }

        $this->subscriptionRepository->save($subscription);

        return $subscription;
    }
}
