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
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Api\OrderPaymentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Session\PurseSessionDataManager;

/**
 * Class ManagePurseDataOnOrderObserverTest
 *
 * @package UpStreamPay\Core\Observer
 */
class ManagePurseDataOnOrderObserverTest extends TestCase
{
    private const PURSE_SESSION_ID_MOCK = 'd11bc29e-54d1-44c5-b0ab-68e2d02c8759';

    private ManagePurseDataOnOrderObserver $managePurseDataOnOrderObserver;
    private Observer&MockObject $observerMock;
    private OrderPayment&MockObject $orderPaymentMock;
    private OrderPaymentRepositoryInterface&MockObject $orderPaymentRepositoryMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->orderPaymentRepositoryMock = self::createMock(OrderPaymentRepositoryInterface::class);
        $this->observerMock = self::createMock(Observer::class);
        $orderMock = self::createMock(Order::class);
        $this->orderPaymentMock = self::createMock(OrderPayment::class);
        $quotePaymentMock = self::createMock(QuotePayment::class);
        $quoteMock = self::createMock(Quote::class);

        $orderMock->method('getPayment')
            ->willReturn($this->orderPaymentMock)
        ;

        $quotePaymentMock->method('getData')
            ->with(PurseSessionDataManager::PAYMENT_PURSE_SESSION_ID)
            ->willReturn(self::PURSE_SESSION_ID_MOCK)
        ;
        $quoteMock->method('getPayment')
            ->willReturn($quotePaymentMock)
        ;

        $this->observerMock->method('getData')
            ->willReturnOnConsecutiveCalls($quoteMock, $orderMock)
        ;

        $this->managePurseDataOnOrderObserver = new ManagePurseDataOnOrderObserver($this->orderPaymentRepositoryMock);
    }

    /**
     * @return void
     */
    public function testExecuteWithUpStreamPayPaymentMethod(): void
    {
        $this->orderPaymentMock->expects(self::once())
            ->method('getMethod')
            ->willReturn(Config::METHOD_CODE_UPSTREAM_PAY)
        ;

        $this->orderPaymentMock->expects(self::once())
            ->method('setData')
            ->with(PurseSessionDataManager::PAYMENT_PURSE_SESSION_ID, self::PURSE_SESSION_ID_MOCK)
            ->willReturnSelf()
        ;

        $this->orderPaymentRepositoryMock->expects(self::once())
            ->method('save')
            ->with($this->orderPaymentMock)
            ->willReturnSelf()
        ;

        $this->managePurseDataOnOrderObserver->execute($this->observerMock);
    }

    /**
     * @return void
     */
    public function testExecuteWithoutUpStreamPayPaymentMethod(): void
    {
        $this->orderPaymentMock->expects(self::once())
            ->method('getMethod')
            ->willReturn('paypal')
        ;

        $this->orderPaymentMock->expects(self::never())
            ->method('setData')
        ;

        $this->orderPaymentRepositoryMock->expects(self::never())
            ->method('save')
        ;

        $this->managePurseDataOnOrderObserver->execute($this->observerMock);
    }
}
