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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use UpStreamPay\Core\Exception\WrongSubscriptionCancelMethodException;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Subscription\CancelService;

/**
 * Class CancelPurseSubscriptionObserver
 *
 * @package UpStreamPay\Core\Observer
 */
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

    /**
     * @param Observer $observer
     *
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws WrongSubscriptionCancelMethodException
     */
    public function execute(Observer $observer): void
    {
        if ($this->config->getSubscriptionPaymentEnabled()) {
            $subscriptionId = $observer->getData('subscriptionId');

            if ($subscriptionId) {
                $this->cancelService->execute($subscriptionId);
            }
        }
    }
}
