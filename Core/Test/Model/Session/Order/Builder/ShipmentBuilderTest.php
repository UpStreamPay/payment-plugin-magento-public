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

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\MockObject\MockObject;
use UpStreamPay\Core\Model\Session\Order\AddressBuilderInterface;
use UpStreamPay\Core\Model\Session\Order\Builder\ShipmentBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Class ShipmentBuilderTest
 *
 * @package UpStreamPay\Core\Test\Model\Session\Order\Builder
 */
class ShipmentBuilderTest extends TestCase
{
    private Quote&MockObject $quoteMock;
    private ShipmentBuilder $shipmentBuilder;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        //Because of magic methods.
        $methods = array_merge(
            get_class_methods(Address::class),
            ['getBaseShippingInclTax', 'getBaseShippingAmount', 'getBaseShippingTaxAmount']
        );
        $shippingAddressMock = self::createPartialMock(Address::class, $methods);
        $shippingAddressMock->method('getBaseShippingInclTax')
            ->willReturn(6.00)
        ;
        $shippingAddressMock->method('getBaseShippingAmount')
            ->willReturn(5.00)
        ;
        $shippingAddressMock->method('getBaseShippingTaxAmount')
            ->willReturn(1.00)
        ;
        $shippingAddressMock->method('getShippingMethod')
            ->willReturn('colissimo')
        ;

        $this->quoteMock = self::createMock(Quote::class);
        $this->quoteMock->method('getShippingAddress')
            ->willReturn($shippingAddressMock)
        ;


        $addressBuilderMock = self::createMock(AddressBuilderInterface::class);
        $addressBuilderMock->method('execute')
            ->willReturn(['address_data'])
        ;

        $eventManagerMock = self::createMock(ManagerInterface::class);
        $scopeConfigMock = self::createMock(ScopeConfigInterface::class);
        $scopeConfigMock->method('getValue')
            ->willReturn('storeName')
        ;

        $this->shipmentBuilder = new ShipmentBuilder(
            [],
            $addressBuilderMock,
            $eventManagerMock,
            $scopeConfigMock
        );
    }

    /**
     * @return void
     */
    public function testExecuteVirtualQuote(): void
    {
        $this->quoteMock->expects(self::exactly(2))
            ->method('getIsVirtual')
            ->willReturn(true)
        ;

        $expectedShipmentData = [
            'delivery_type_code' => 'digital',
            'seller_reference' => 'storeName',
            'shipping_address' => ['address_data']
        ];

        self::assertSame([$expectedShipmentData], $this->shipmentBuilder->execute($this->quoteMock));
    }

    /**
     * @return void
     */
    public function testExecute(): void
    {
        $this->quoteMock->expects(self::exactly(2))
            ->method('getIsVirtual')
            ->willReturn(false)
        ;

        $expectedShipmentData = [
            'delivery_type_code' => 'user_delivery',
            'seller_reference' => 'storeName',
            'amount' => 6.0,
            'net_amount' => 5.0,
            'tax_amount' => 1.0,
            'delivery_method_reference' => 'colissimo',
            'shipping_address' => ['address_data'],
        ];

        self::assertSame([$expectedShipmentData], $this->shipmentBuilder->execute($this->quoteMock));
    }
}
