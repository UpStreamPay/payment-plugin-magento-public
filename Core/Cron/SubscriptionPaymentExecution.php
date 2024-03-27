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
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Subscription\RenewSubscriptionService;
use UpStreamPay\Core\Model\SubscriptionRepository;

/**
 * Class SubscriptionPaymentExecution
 *
 * Cron used to renew & pay the subscription
 *
 * @package UpStreamPay\Core\Cron
 *
 * @codeCoverageIgnore
 */
class SubscriptionPaymentExecution
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Config $config,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly RenewSubscriptionService $renewSubscriptionService
    )
    {
    }

    /**
     * Renew subscription cron.
     *
     * @return void
     */
    public function execute(): void
    {
        if ($this->config->getDebugMode()) {
            $this->logger->debug('Cron renewal process start.');
        }

        if ($this->config->getSubscriptionPaymentEnabled()) {
            try {
                $subscriptionToRenew = $this->subscriptionRepository->getAllSubscriptionsToRenew();
                if (!empty($subscriptionToRenew)) {
                    foreach ($subscriptionToRenew as $subscription) {
                        if ($this->config->getDebugMode()) {
                            $this->logger->debug(
                                sprintf(
                                    'Attempting to renew subscription with ID %s',
                                    $subscription->getEntityId()
                                )
                            );
                        }

                        $this->renewSubscriptionService->execute($subscription);
                    }
                } else {
                    if ($this->config->getDebugMode()) {
                        $this->logger->debug('No subscription to renew found for the day.');
                    }
                }
            } catch (\Throwable $exception) {
                $this->logger->error('Error while trying to run cron to renew the subscription of todays date.');
                $this->logger->error($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);
            }
        } else {
            $this->logger->error('Renew subscription cron is running but the subscription feature is disabled.');
        }

        if ($this->config->getDebugMode()) {
            $this->logger->debug('Cron renewal process done.');
        }
    }
}
