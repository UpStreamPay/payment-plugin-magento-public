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

namespace UpStreamPay\Core\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use UpStreamPay\Core\Model\Subscription\CancelService;
use UpStreamPay\Core\Model\Config;

class CancelPurseSubscriptionObserver implements ObserverInterface
{
    /**
     * @param CancelService $cancelService
     * @param Config $config
     */
    public function __construct(
        private readonly CancelService $cancelService,
        private readonly Config $config
    )
    {}

    public function execute(Observer $observer): void
    {
        if ($this->config->getSubscriptionPaymentEnabled()) {
            $subscriptionId = $observer->getData('subscription_id');
            $creditMemo = $observer->getData('creditmemo');

            if ($subscriptionId) {
                $this->cancelService->execute($subscriptionId);
            } elseif ($creditMemo) {
                $this->cancelService->execute(null, $creditMemo);
            }
        }
    }

}
