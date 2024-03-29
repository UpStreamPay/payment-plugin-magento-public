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
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
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
     * @param TimezoneInterface $timezoneInterface
     */
    public function __construct(
        private readonly SubscriptionFactory                  $subscriptionFactory,
        private readonly SubscriptionRepositoryInterface      $subscriptionRepository,
        private readonly ProductRepositoryInterface           $productRepository,
        private readonly ManagerInterface                     $eventManager,
        private readonly Config                               $config,
        private readonly LoggerInterface                      $logger,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository,
        private readonly TimezoneInterface                    $timezoneInterface,
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
     * @throws NoTransactionsException|LocalizedException
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

            // if simple product, only get baseRowTotalInclTax from invoice
            $baseRowTotalInclTax = (float)$invoiceItem->getBaseRowTotalInclTax();

            // Check if product is a child of configurable
            if (!$baseRowTotalInclTax || $baseRowTotalInclTax == 0.0) {
                // Always take the configurable cost
                $baseRowTotalInclTax = $this->getParentProductPrice($order, $invoice, $invoiceItem->getSku());
            }

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
                        (int)$invoice->getEntityId(),
                        $product,
                        $transactionId,
                        $subscriptionDurationAttrCode,
                        $baseRowTotalInclTax,
                        (int)$invoiceItem->getQty(),
                        (int)$order->getCustomerId()
                    );
                    $futureSubscription = $this->createAndSaveSubscription(
                        $incrementId,
                        null,
                        null,
                        $product,
                        $transactionId,
                        $subscriptionDurationAttrCode,
                        $baseRowTotalInclTax,
                        (int)$invoiceItem->getQty(),
                        (int)$order->getCustomerId(),
                        true,
                        $subscription->getEntityId(),
                    );
                    $this->eventManager->dispatch(
                        'new_usp_subscription_saved',
                        [
                            'subscription' => $subscription,
                            'future_subscription' => $futureSubscription,
                        ]
                    );
                } catch (Throwable $exception) {
                    $this->createSubscriptionError($incrementId, $exception);
                }
            } else {
                //This means we just paid an invoice, and we already have at least 1 subscription line for this invoice
                //item. We are renewing a subscription, meaning we have to create the future subscription.
                $subscriptions = $this->subscriptionRepository->getByOrderId((int)$order->getEntityId());

                foreach ($subscriptions as $subscription) {
                    if ($subscription->getSubscriptionStatus() !== Subscription::EXPIRED) {
                        //The previous subscription has been expired in the renewal process, so the only matching
                        //subscription for the given order id is the subscription we just paid because in one renewal
                        //order we currently only handle one subscription only.
                        try {
                            $subscription
                                ->setPaymentStatus(Subscription::PAID)
                                ->setNextPaymentDate(null)
                                ->setInvoiceId((int)$invoice->getEntityId());

                            $this->subscriptionRepository->save($subscription);

                            //Create the future subscription.
                            $futureSubscription = $this->createAndSaveSubscription(
                                $incrementId,
                                null,
                                null,
                                $product,
                                $subscription->getOriginalTransactionId(),
                                $subscriptionDurationAttrCode,
                                $baseRowTotalInclTax,
                                (int)$invoiceItem->getQty(),
                                (int)$order->getCustomerId(),
                                true,
                                $subscription->getEntityId()
                            );

                            $this->eventManager->dispatch(
                                'new_usp_subscription_saved',
                                [
                                    'subscription' => $subscription,
                                    'future_subscription' => $futureSubscription,
                                ]
                            );
                        } catch (Throwable $exception) {
                            $this->createSubscriptionError($incrementId, $exception);
                        }
                    }
                }
            }
        }
    }

    /**
     * Return the parent product baseRowTotalInclTax from invoice item
     * @param OrderInterface $order
     * @param Invoice $invoice
     * @param string $invoiceSku
     * @return float|null
     */
    private function getParentProductPrice(OrderInterface $order, Invoice $invoice, string $invoiceSku): ?float
    {
        foreach ($order->getItems() as $orderItem) {
            if ($orderItem->getProductType() === Configurable::TYPE_CODE && $orderItem->getSku() == $invoiceSku) {
                foreach ($invoice->getItems() as $invoiceItem) {
                    if ($invoiceItem->getProductId() == $orderItem->getProductId() && $invoiceItem->getSku() == $invoiceSku) {
                        return (float)$invoiceItem->getBaseRowTotalInclTax();
                    }
                }
            }
        }
        return null;
    }

    /**
     * @param string $incrementId
     * @param null|int $orderId
     * @param null|int $invoiceId
     * @param ProductInterface $product
     * @param string $transactionId
     * @param string $subscriptionDurationAttrCode
     * @param float $subscriptionTotal
     * @param int $qty
     * @param null|int $customerId
     * @param bool $future
     * @param null|int $parentSubscriptionId
     *
     * @return SubscriptionInterface
     * @throws LocalizedException
     */
    public function createAndSaveSubscription(
        string           $incrementId,
        ?int             $orderId,
        ?int             $invoiceId,
        ProductInterface $product,
        string           $transactionId,
        string           $subscriptionDurationAttrCode,
        float            $subscriptionTotal,
        int              $qty,
        ?int             $customerId = null,
        bool             $future = false,
        ?int             $parentSubscriptionId = null,
    ): SubscriptionInterface
    {
        /** @var SubscriptionInterface $subscription */
        $subscription = $this->subscriptionFactory->create()
            ->setSubscriptionIdentifier($product->getSku() . '_' . $incrementId)
            ->setProductPrice($subscriptionTotal)
            ->setQty($qty)
            ->setProductName($product->getName())
            ->setProductSku($product->getSku())
            ->setInvoiceId($invoiceId)
            ->setOriginalTransactionId($transactionId);

        if ($customerId) {
            $subscription->setCustomerId((int)$customerId);
        }

        /* set first subscription dates */
        $startDate = $this->timezoneInterface->date(new \DateTime())->format('Y-m-d');
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
                ->setNextPaymentDate($futureStartDate)
                ->setParentSubscriptionId($parentSubscriptionId);
        }

        $this->subscriptionRepository->save($subscription);

        return $subscription;
    }

    /**
     * @param string $incrementId
     * @param Throwable $exception
     *
     * @return void
     * @throws CreateSubscriptionException
     */
    private function createSubscriptionError(string $incrementId, Throwable $exception): void
    {
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
}
