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

use Magento\Framework\Exception\LocalizedException;
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
        private readonly LoggerInterface          $logger,
        private readonly Config                   $config,
        private readonly SubscriptionRepository   $subscriptionRepository,
        private readonly RenewSubscriptionService $renewSubscriptionService
    )
    {
    }

    /**
     * Write to system.log
     *
     * @return void
     * @throws LocalizedException
     */
    public function execute(): void
    {
        if ($this->config->getSubscriptionPaymentEnabled()) {
            $subscriptionToRenew = $this->subscriptionRepository->getAllSubscriptionsToRenew();
            if (!empty($subscriptionToRenew)) {
                foreach ($subscriptionToRenew as $subscription) {
                    /** Call renewSubService */
                    $this->renewSubscriptionService->execute($subscription);
                }
            }


            //TODO implement proper logic (should only call one service).
            $this->logger->info('Cron Works');
        }
    }
}
