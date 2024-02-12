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

class SubscriptionPaymentExecution {
    public function __construct(private readonly LoggerInterface $logger)
    {}

    /**
     * Write to system.log
     *
     * @return void
     */
    public function execute()
    {
        $this->logger->info('Cron Works');
    }
}
