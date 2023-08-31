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

use Magento\Directory\Model\Region;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\MockObject\MockObject;
use UpStreamPay\Core\Model\Session\Order\AddressBuilderInterface;
use UpStreamPay\Core\Model\Session\Order\Builder\AddressBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Class AddressBuilderTest
 *
 * @package UpStreamPay\Core\Test\Model\Session\Order\Builder
 */
class AddressBuilderTest extends TestCase
{
    private Quote&MockObject $quoteMock;
    private Address&MockObject $addressMock;
    private AddressBuilder $addressBuilder;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        //Because of magic methods.
        $methods = array_merge(
            get_class_methods(Quote::class),
            ['getCustomerEmail']
        );
        $this->quoteMock = self::createPartialMock(Quote::class, $methods);
        $this->addressMock = self::createMock(Address::class);
        $this->addressBuilder = new AddressBuilder();
    }

    /**
     * @return void
     */
    public function testExecuteBillingAddress(): void
    {
        $this->quoteMock->expects(self::once())
            ->method('getCustomerEmail')
            ->willReturn('random@email.com')
        ;
        $this->quoteMock->expects(self::once())
            ->method('getBillingAddress')
            ->willReturn($this->addressMock)
        ;

        $this->addressMock->expects(self::once())
            ->method('getFirstname')
            ->willReturn('firstNameBilling')
        ;
        $this->addressMock->expects(self::once())
            ->method('getMiddlename')
            ->willReturn('middleNameBilling')
        ;
        $this->addressMock->expects(self::once())
            ->method('getLastname')
            ->willReturn('lastNameBilling')
        ;
        $this->addressMock->expects(self::once())
            ->method('getCompany')
            ->willReturn('companyBilling')
        ;
        $this->addressMock->expects(self::once())
            ->method('getStreet')
            ->willReturn('streetBilling')
        ;
        $this->addressMock->expects(self::once())
            ->method('getCity')
            ->willReturn('cityBilling')
        ;
        $this->addressMock->expects(self::once())
            ->method('getPostcode')
            ->willReturn('postCodeBilling')
        ;
        $this->addressMock->expects(self::exactly(2))
            ->method('getCountryId')
            ->willReturn('countryIdBilling')
        ;
        $this->addressMock->expects(self::exactly(2))
            ->method('getRegionId')
            ->willReturn('regionId')
        ;
        $this->addressMock->expects(self::once())
            ->method('getTelephone')
            ->willReturn('telephoneBilling')
        ;

        //Because of magic methods.
        $methods = array_merge(
            get_class_methods(Region::class),
            ['getCode']
        );
        $regionModelMock = self::createPartialMock(Region::class, $methods);
        $regionModelMock->expects(self::once())
            ->method('getCode')
            ->willReturn('regionCode')
        ;

        $this->addressMock->expects(self::once())
            ->method('getRegionModel')
            ->willReturn($regionModelMock)
        ;

        $expectedAddressData = [
            'first_name' => 'firstNameBilling',
            'middle_name' => 'middleNameBilling',
            'last_name' => 'lastNameBilling',
            'company' => 'companyBilling',
            'address_lines' => 'streetBilling',
            'city' => 'cityBilling',
            'postal_code' => 'postCodeBilling',
            'country_code' => 'countryIdBilling',
            'province_code' => 'countryidbilling-regionCode',
            'email' => 'random@email.com',
            'phone' => 'telephoneBilling',
        ];

        self::assertSame($expectedAddressData, $this->addressBuilder->execute($this->quoteMock));
    }

    /**
     * @return void
     */
    public function testExecuteShippingAddress(): void
    {
        $this->quoteMock->expects(self::once())
            ->method('getCustomerEmail')
            ->willReturn('random@email.com')
        ;
        $this->quoteMock->expects(self::once())
            ->method('getShippingAddress')
            ->willReturn($this->addressMock)
        ;

        $this->addressMock->expects(self::once())
            ->method('getFirstname')
            ->willReturn('firstNameShipping')
        ;
        $this->addressMock->expects(self::once())
            ->method('getMiddlename')
            ->willReturn('middleNameShipping')
        ;
        $this->addressMock->expects(self::once())
            ->method('getLastname')
            ->willReturn('lastNameShipping')
        ;
        $this->addressMock->expects(self::once())
            ->method('getCompany')
            ->willReturn('companyShipping')
        ;
        $this->addressMock->expects(self::once())
            ->method('getStreet')
            ->willReturn('streetShipping')
        ;
        $this->addressMock->expects(self::once())
            ->method('getCity')
            ->willReturn('cityShipping')
        ;
        $this->addressMock->expects(self::once())
            ->method('getPostcode')
            ->willReturn('postCodeShipping')
        ;
        $this->addressMock->expects(self::once())
            ->method('getCountryId')
            ->willReturn('countryIdShipping')
        ;
        $this->addressMock->expects(self::once())
            ->method('getRegionId')
            ->willReturn(null)
        ;
        $this->addressMock->expects(self::once())
            ->method('getTelephone')
            ->willReturn('telephoneShipping')
        ;

        $expectedAddressData = [
            'first_name' => 'firstNameShipping',
            'middle_name' => 'middleNameShipping',
            'last_name' => 'lastNameShipping',
            'company' => 'companyShipping',
            'address_lines' => 'streetShipping',
            'city' => 'cityShipping',
            'postal_code' => 'postCodeShipping',
            'country_code' => 'countryIdShipping',
            'email' => 'random@email.com',
            'phone' => 'telephoneShipping',
        ];

        self::assertSame(
            $expectedAddressData,
            $this->addressBuilder->execute($this->quoteMock, AddressBuilderInterface::SHIPPING_ADDRESS)
        );
    }

    /**
     * @return void
     */
    public function testExecuteVirtual(): void
    {
        $this->quoteMock->expects(self::once())
            ->method('getCustomerEmail')
            ->willReturn('random@email.com')
        ;

        $this->quoteMock->expects(self::once())
            ->method('getIsVirtual')
            ->willReturn(true)
        ;

        $expectedAddressData = [
            'email' => 'random@email.com',
        ];

        self::assertSame(
            $expectedAddressData,
            $this->addressBuilder->execute($this->quoteMock, AddressBuilderInterface::SHIPPING_ADDRESS)
        );
    }
}
