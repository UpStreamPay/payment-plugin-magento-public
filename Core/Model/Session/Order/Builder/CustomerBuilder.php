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

use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Directory\Helper\Data;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use UpStreamPay\Core\Model\Session\Order\AddressBuilderInterface;
use UpStreamPay\Core\Model\Session\Order\BuilderInterface;

/**
 * Class CustomerBuilder
 *
 * @package UpStreamPay\Core\Model\Session\Order\Builder
 */
class CustomerBuilder implements BuilderInterface
{
    /**
     * @param GroupRepositoryInterface $groupRepository
     * @param ScopeConfigInterface $config
     * @param TimezoneInterface $timezone
     * @param BuilderInterface $accountBuilder
     * @param AddressBuilderInterface $billingAddressBuilder
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly ScopeConfigInterface $config,
        private readonly TimezoneInterface $timezone,
        private readonly BuilderInterface $accountBuilder,
        private readonly AddressBuilderInterface $billingAddressBuilder,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * Build customer data for the UpStream Pay order.
     *
     * @param CartInterface $quote
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     *
     * @see UpStream Pay documentation regarding all the fields & format.
     */
    public function execute(CartInterface $quote): array
    {
        $customerData = [];

        if ($quote->getCustomerIsGuest()) {
            $customerData['reference'] = '';
        } else {
            $customer = $quote->getCustomer();
            $customerGroup = $this->groupRepository->getById($customer->getGroupId());
            $customerDob = $quote->getCustomerDob();

            $customerData['reference'] = $customer->getId();

            //Based on Magento default customer groups. In case of custom group, the field is not sent.
            if ($customerGroup && $customerGroup->getId()) {
                if ($customerGroup->getCode() === 'General') {
                    $customerData['type_code'] = 'customer';
                } elseif ($customerGroup->getCode() === 'Retailer' || $customerGroup->getCode() === 'Wholesale') {
                    $customerData['type_code'] = 'business';
                }
            }

            if ($customerDob !== null) {
                $customerData['birthdate'] = $this->timezone->date(strtotime($customerDob))->format('Y-m-d');
            }
        }

        $customerData['company_name'] = $quote->getBillingAddress()->getCompany();
        $customerData['first_name'] = $quote->getBillingAddress()->getFirstname();
        $customerData['middle_name'] = $quote->getBillingAddress()->getMiddlename();
        $customerData['last_name'] = $quote->getBillingAddress()->getLastname();
        $customerData['ip'] = $quote->getRemoteIp();
        $customerData['locale_code'] = str_replace(
            '_',
            '-',
            $this->config->getValue(
                Data::XML_PATH_DEFAULT_LOCALE,
                ScopeInterface::SCOPE_STORE,
                $this->storeManager->getStore()->getId()
            )
        );

        $customerData['billing_address'] = $this->billingAddressBuilder->execute($quote);
        $customerData['account'] = $this->accountBuilder->execute($quote);

        //Native Magento doesn't have this information.
        $customerData['additional_attributes']['national_identifier'] = '';

        return $customerData;
    }
}
