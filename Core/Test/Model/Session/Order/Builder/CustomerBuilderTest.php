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

namespace UpStreamPay\Core\Test\Model\Session\Order\Builder;

use DateTime;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\AttributeInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Model\Quote;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Session\Order\AddressBuilderInterface;
use UpStreamPay\Core\Model\Session\Order\Builder\CustomerBuilder;
use PHPUnit\Framework\TestCase;
use UpStreamPay\Core\Model\Session\Order\BuilderInterface;

/**
 * Class CustomerBuilderTest
 *
 * @package UpStreamPay\Core\Test\Model\Session\Order\Builder
 */
class CustomerBuilderTest extends TestCase
{
    private GroupInterface&MockObject $customerGroupMock;
    private Quote&MockObject $quoteMock;
    private CustomerBuilder $customerBuilder;
    private Config $configMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        //Because of magic methods.
        $methods = array_merge(
            get_class_methods(Quote::class),
            ['getRemoteIp', 'getCustomerDob']
        );

        $groupRepositoryMock = self::createMock(GroupRepositoryInterface::class);
        $scopeConfigMock = self::createMock(ScopeConfigInterface::class);
        $timezoneMock = self::createMock(TimezoneInterface::class);
        $accountBuilderMock = self::createMock(BuilderInterface::class);
        $billingAddressBuilderMock = self::createMock(AddressBuilderInterface::class);
        $storeManagerMock = self::createMock(StoreManagerInterface::class);
        $this->customerGroupMock = self::createMock(GroupInterface::class);
        $this->quoteMock = self::createPartialMock(Quote::class, $methods);
        $this->configMock = self::createMock(Config::class);

        $storeMock = self::createMock(StoreInterface::class);
        $storeMock->method('getId')
            ->willReturn(123)
        ;

        $storeManagerMock->method('getStore')
            ->willReturn($storeMock)
        ;

        $billingAddressBuilderMock->method('execute')
            ->willReturn(['billing_address_data'])
        ;

        $accountBuilderMock->method('execute')
            ->willReturn(['account_data'])
        ;

        $timezoneMock->method('date')
            ->willReturn(new DateTime('2023-01-01 00:00:00'))
        ;

        $scopeConfigMock->method('getValue')
            ->willReturn('en_US')
        ;

        $groupRepositoryMock->method('getById')
            ->willReturn($this->customerGroupMock)
        ;

        $billingAddressMock = self::createMock(AddressInterface::class);
        $billingAddressMock->method('getCompany')
            ->willReturn('company')
        ;
        $billingAddressMock->method('getFirstname')
            ->willReturn('firstName')
        ;
        $billingAddressMock->method('getMiddlename')
            ->willReturn('middleName')
        ;
        $billingAddressMock->method('getLastname')
            ->willReturn('lastName')
        ;

        $attributeMock = self::createMock(AttributeInterface::class);
        $attributeMock->method('getValue')
            ->willReturn('123TIN')
        ;

        $customerMock = self::createMock(CustomerInterface::class);
        $customerMock->method('getId')
            ->willReturn(123)
        ;
        $customerMock->method('getGroupId')
            ->willReturn(123)
        ;
        $customerMock->method('getCustomAttribute')
            ->willReturn($attributeMock)
        ;

        $this->quoteMock->method('getCustomerDob')
            ->willReturn('2023-01-01 00:00:00')
        ;
        $this->quoteMock->method('getBillingAddress')
            ->willReturn($billingAddressMock)
        ;
        $this->quoteMock->method('getCustomer')
            ->willReturn($customerMock)
        ;
        $this->quoteMock->method('getRemoteIp')
            ->willReturn('127.0.0.1')
        ;
        $this->quoteMock->method('getId')
            ->willReturn('123')
        ;

        $this->customerBuilder = new CustomerBuilder(
            $groupRepositoryMock,
            $scopeConfigMock,
            $timezoneMock,
            $accountBuilderMock,
            $billingAddressBuilderMock,
            $storeManagerMock,
            $this->configMock
        );
    }

    /**
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testExecuteGuest(): void
    {
        $this->quoteMock->expects(self::once())
            ->method('getCustomerIsGuest')
            ->willReturn(true)
        ;

        $expectedCustomerData = [
            'reference' => 'guest-123',
            'company_name' => 'company',
            'first_name' => 'firstName',
            'middle_name' => 'middleName',
            'last_name' => 'lastName',
            'ip' => '127.0.0.1',
            'locale_code' => 'en-US',
            'billing_address' => ['billing_address_data'],
            'account' => ['account_data'],
        ];
        self::assertSame($expectedCustomerData, $this->customerBuilder->execute($this->quoteMock));
    }

    /**
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testExecuteCustomer(): void
    {
        $this->quoteMock->expects(self::once())
            ->method('getCustomerIsGuest')
            ->willReturn(false)
        ;

        $this->customerGroupMock->expects(self::once())
            ->method('getId')
            ->willReturn(123)
        ;
        $this->customerGroupMock->expects(self::atMost(2))
            ->method('getCode')
            ->willReturn('General')
        ;

        $this->configMock->expects(self::once())
            ->method('getCustomerTinAttributeCode')
            ->willReturn('tin')
        ;

        $expectedCustomerData = [
            'reference' => 123,
            'type_code' => 'customer',
            'birthdate' => '2023-01-01',
            'additional_attributes' => ['national_identifier' => '123TIN'],
            'company_name' => 'company',
            'first_name' => 'firstName',
            'middle_name' => 'middleName',
            'last_name' => 'lastName',
            'ip' => '127.0.0.1',
            'locale_code' => 'en-US',
            'billing_address' => ['billing_address_data'],
            'account' => ['account_data'],
        ];
        self::assertSame($expectedCustomerData, $this->customerBuilder->execute($this->quoteMock));
    }

    /**
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function testExecuteCustomerBusiness(): void
    {
        $this->quoteMock->expects(self::once())
            ->method('getCustomerIsGuest')
            ->willReturn(false)
        ;

        $this->customerGroupMock->expects(self::once())
            ->method('getId')
            ->willReturn(123)
        ;
        $this->customerGroupMock->expects(self::atMost(2))
            ->method('getCode')
            ->willReturn('Retailer')
        ;

        $this->configMock->expects(self::once())
            ->method('getCustomerTinAttributeCode')
            ->willReturn(null)
        ;

        $expectedCustomerData = [
            'reference' => 123,
            'type_code' => 'business',
            'birthdate' => '2023-01-01',
            'additional_attributes' => ['national_identifier' => ''],
            'company_name' => 'company',
            'first_name' => 'firstName',
            'middle_name' => 'middleName',
            'last_name' => 'lastName',
            'ip' => '127.0.0.1',
            'locale_code' => 'en-US',
            'billing_address' => ['billing_address_data'],
            'account' => ['account_data'],
        ];
        self::assertSame($expectedCustomerData, $this->customerBuilder->execute($this->quoteMock));
    }
}
