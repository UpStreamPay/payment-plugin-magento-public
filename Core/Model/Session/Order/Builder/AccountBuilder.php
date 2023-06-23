<?php
/**
 * UpStream Pay
 *
 * Copyright (c) 2019-2023 UpStream Pay.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Claranet France <info@fr.clara.net>
 */
declare(strict_types=1);

namespace UpStreamPay\Core\Model\Session\Order\Builder;

use Magento\Customer\Model\Logger;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Api\Data\CartInterface;
use UpStreamPay\Core\Model\Session\Order\BuilderInterface;

/**
 * Class AccountBuilder
 *
 * @package UpStreamPay\Core\Model\Session\Order\Builder
 */
class AccountBuilder implements BuilderInterface
{
    /**
     * @param TimezoneInterface $timezone
     * @param Logger $logger
     */
    public function __construct(
        private readonly TimezoneInterface $timezone,
        private readonly Logger $logger
    ) {
    }

    /**
     * Build the account data for the UpStream Pay order.
     *
     * @param CartInterface $quote
     *
     * @return array
     *
     * @see UpStream Pay documentation regarding all the fields & format.
     */
    public function execute(CartInterface $quote): array
    {
        $accountData = [];

        if ($quote->getCustomerIsGuest()) {
            //Guest date has to be current date with ISO 8601 date
            $guestDate = $this->timezone->date()->format('c');

            $accountData['creation_date_time'] = $guestDate;
            $accountData['update_date_time'] = $guestDate;
            $accountData['authentication_method'] = 'GUEST';
            $accountData['age_indicator'] = 'GUEST';
            $accountData['change_indicator'] = 'NEW';
        } else {
            $customer = $quote->getCustomer();
            $createdAt = strtotime($customer->getCreatedAt());
            $updatedAt = strtotime($customer->getUpdatedAt());

            $accountData['creation_date_time'] = $this->timezone->date($createdAt)->format('c');
            $accountData['update_date_time'] = $this->timezone->date($updatedAt)->format('c');
            $accountData['authentication_method'] = 'MERCHANT_CREDENTIALS';
            $accountData['authentication_date_time'] = $this->timezone->date(
                strtotime($this->logger->get($customer->getId())->getLastLoginAt())
            )->format('c');

            $daysSinceCreation = $this->timezone->date()->diff($this->timezone->date($createdAt))->days;
            $daysSinceupdate = $this->timezone->date()->diff($this->timezone->date($updatedAt))->days;

            $accountData['age_indicator'] = $this->getDaysSince($daysSinceCreation);
            $accountData['change_indicator'] = $this->getDaysSince($daysSinceupdate);
        }

        return $accountData;
    }

    /**
     * Get the number of days since the account creation or update.
     *
     * @param int $numberOfDays
     *
     * @return string
     */
    private function getDaysSince(int $numberOfDays): string
    {
        return match (true) {
            $numberOfDays === 0 => 'NEW',
            $numberOfDays < 30 => 'LESS_30_DAYS',
            $numberOfDays >= 30 && $numberOfDays < 60 => 'BETWEEN_30_60_DAYS',
            default => 'MORE_60_DAYS',
        };
    }
}
