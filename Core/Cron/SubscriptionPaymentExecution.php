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

/**
 * Class SubscriptionPaymentExecution
 *
 * Cron used to renew & pay the subscription
 *
 * @package UpStreamPay\Core\Cron
 *
 * @codeCoverageIgnore
 */
class SubscriptionPaymentExecution {
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Config $config
    )
    {}

    /**
     * Write to system.log
     *
     * @return void
     */
    public function execute(): void
    {
        if ($this->config->getSubscriptionPaymentEnabled()) {
            //TODO implement proper logic (should only call one service).
            $this->logger->info('Cron Works');
        }
    }
}
