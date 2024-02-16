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

namespace UpStreamPay\Core\Model\Session\Order\Builder;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote\Address;
use UpStreamPay\Core\Model\Session\Order\AddressBuilderInterface;

/**
 * Class AddressBuilder
 *
 * @package UpStreamPay\Core\Model\Session\Order\Builder
 */
class AddressBuilder implements AddressBuilderInterface
{
    /**
     * Build the address data for the UpStream Pay order.
     *
     * @param CartInterface $quote
     * @param string $addressType
     *
     * @return array
     *
     * @see UpStream Pay documentation regarding all the fields & format.
     */
    public function execute(CartInterface $quote, string $addressType = self::BILLING_ADDRESS): array
    {
        $addressData = [];
        $customerEmail = $quote->getCustomerEmail();

        //When we have a guest customer, the email address is only on the billing address at this point of the checkout.
        //But it's only available after a payment method has been set. So in order to avoid rewriting Magento, we use
        //the email provided by the frontend. On the next call with the same cart, the DB will be up-to-date,
        //thus using the DB value.
        if ($quote->getCustomerIsGuest()) {
            $customerEmail = $quote->getBillingAddress()->getEmail() ?? $quote->getData('guest_email');
        }

        /** @var Address $address */
        if ($addressType === self::BILLING_ADDRESS) {
            $address = $quote->getBillingAddress();
        } else {
            if ($quote->getIsVirtual()) {
                //It's the only information that does not rely on a shipping address.
                return ['email' => $customerEmail];
            }

            $address = $quote->getShippingAddress();
        }

        $addressData['first_name'] = $address->getFirstname();
        $addressData['middle_name'] = $address->getMiddlename();
        $addressData['last_name'] = $address->getLastname();
        $addressData['company'] = $address->getCompany();
        $addressData['address_lines'] = $address->getStreet();
        $addressData['city'] = $address->getCity();
        $addressData['postal_code'] = $address->getPostcode();
        $addressData['country_code'] = $address->getCountryId();

        if ($address->getRegionId() !== null) {
            $addressData['province_code'] = sprintf('%s-%s',
                mb_strtolower($address->getCountryId()),
                $address->getRegionModel($address->getRegionId())->getCode()
            );
        }

        $addressData['email'] = $customerEmail;
        $addressData['phone'] = $address->getTelephone();

        return $addressData;
    }
}
