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

namespace UpStreamPay\Core\Test\Model\Session\Order;

use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\MockObject\MockObject;
use UpStreamPay\Core\Model\Session\Order\OrderService;
use PHPUnit\Framework\TestCase;

/**
 * Class OrderServiceTest
 *
 * @package UpStreamPay\Core\Test\Model\Session\Order
 */
class OrderServiceTest extends TestCase
{
    private UrlInterface&MockObject $urlMock;
    private OrderService $orderService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->urlMock = self::createMock(UrlInterface::class);

        $this->orderService = new OrderService(
            [],
            $this->urlMock
        );
    }

    /**
     * @return void
     */
    public function testExecute(): void
    {
        //Because of magic methods.
        $methods = array_merge(
            get_class_methods(Address::class),
            ['getBaseShippingAmount']
        );
        $shippingAddressMock = self::createPartialMock(Address::class, $methods);
        $shippingAddressMock->expects(self::once())
            ->method('getBaseShippingAmount')
            ->willReturn(5.00)
        ;
        //Because of magic methods.
        $methods = array_merge(
            get_class_methods(Quote::class),
            ['getBaseSubtotalWithDiscount', 'getBaseGrandTotal', 'getBaseCurrencyCode']
        );
        $quoteMock = self::createPartialMock(Quote::class, $methods);
        $quoteMock->expects(self::once())
            ->method('getBaseSubtotalWithDiscount')
            ->willReturn(50.00)
        ;
        $quoteMock->expects(self::once())
            ->method('getShippingAddress')
            ->willReturn($shippingAddressMock)
        ;
        $quoteMock->expects(self::once())
            ->method('getId')
            ->willReturn('123')
        ;
        $quoteMock->expects(self::exactly(3))
            ->method('getBaseGrandTotal')
            ->willReturn(60.00)
        ;
        $quoteMock->expects(self::once())
            ->method('getBaseCurrencyCode')
            ->willReturn('EUR')
        ;

        $this->urlMock->expects(self::exactly(2))
            ->method('getUrl')
            ->willReturnOnConsecutiveCalls('notification/url', 'return/url')
        ;

        $order = $this->orderService->execute($quoteMock);

        $expectedOrder = [
            'hook' => 'notification/url',
            'amount' => 60.00,
            'order' => [
                'redirection' => 'return/url',
                'reference' => '123',
                'amount' => 60.00,
                'net_amount' => 55.00,
                'tax_amount' => 5.00,
                'currency_code' => 'EUR'
            ]
        ];

        self::assertSame($expectedOrder, $order);
    }
}
