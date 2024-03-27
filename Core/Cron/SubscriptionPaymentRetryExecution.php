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

namespace UpStreamPay\Core\Cron;

use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Api\SubscriptionRetryRepositoryInterface;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Subscription\RetryService;

/**
 * Class SubscriptionPaymentRetryExecution
 *
 * Cron used to retry to pay the subscription
 *
 * @package UpStreamPay\Core\Cron
 *
 * @codeCoverageIgnore
 */
class SubscriptionPaymentRetryExecution {

    /**
     * @param LoggerInterface $logger
     * @param Config $config
     * @param SubscriptionRetryRepositoryInterface $subscriptionRetryRepository
     * @param RetryService $retryService
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Config $config,
        private readonly SubscriptionRetryRepositoryInterface $subscriptionRetryRepository,
        private readonly RetryService $retryService
    )
    {}

    /**
     * Retry payment of subscriptions.
     *
     * @return void
     */
    public function execute(): void
    {
        if ($this->config->getDebugMode()) {
            $this->logger->debug('Cron retry process start.');
        }

        if ($this->config->getSubscriptionPaymentEnabled()) {
            try {
                $subscriptionsRetry = $this->subscriptionRetryRepository->getAllSubscriptionToRetryPayment();
                if (!empty($subscriptionsRetry)) {
                    foreach ($subscriptionsRetry as $retry) {
                        if ($this->config->getDebugMode()) {
                            $this->logger->debug(
                                sprintf(
                                    'Attempting to retry subscription with ID %s',
                                    $retry->getEntityId()
                                )
                            );
                        }

                        $this->retryService->execute($retry);
                    }
                } else {
                    if ($this->config->getDebugMode()) {
                        $this->logger->debug('No subscription to retry found.');
                    }
                }
            } catch (\Throwable $exception) {
                $this->logger->error('Error while trying to run cron to retry the subscriptions payment.');
                $this->logger->error($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);
            }
        } else {
            $this->logger->error('Retry subscriptions payment cron is running but the subscription feature is disabled.');
        }

        if ($this->config->getDebugMode()) {
            $this->logger->debug('Cron retry process done.');
        }
    }
}
