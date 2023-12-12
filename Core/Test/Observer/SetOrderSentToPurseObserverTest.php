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
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment as OrderPayment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use UpStreamPay\Core\Model\Config;

/**
 * Class SetOrderSentToPurseObserverTest
 *
 * @package UpStreamPay\Core\Observer
 */
class SetOrderSentToPurseObserverTest extends TestCase
{
    private OrderRepositoryInterface&MockObject $orderRepositoryMock;
    private Observer&MockObject $observerMock;
    private OrderPayment&MockObject $orderPaymentMock;
    private SetOrderSentToPurseObserver $setOrderSentToPurseObserver;
    private Order&MockObject $orderMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->orderRepositoryMock = self::createMock(OrderRepositoryInterface::class);
        $this->orderMock = self::createMock(Order::class);
        $this->orderPaymentMock = self::createMock(OrderPayment::class);
        $this->observerMock = self::createMock(Observer::class);

        $this->orderMock->method('getPayment')
            ->willReturn($this->orderPaymentMock)
        ;

        $this->observerMock->method('getData')
            ->willReturn($this->orderMock)
        ;

        $this->setOrderSentToPurseObserver = new SetOrderSentToPurseObserver($this->orderRepositoryMock);
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

        $this->orderMock->expects(self::once())
            ->method('setData')
            ->with(SetOrderSentToPurseObserver::ORDER_SENT_TO_PURSE, 1)
            ->willReturnSelf()
        ;

        $this->orderRepositoryMock->expects(self::once())
            ->method('save')
            ->with($this->orderMock)
            ->willReturnSelf()
        ;

        $this->setOrderSentToPurseObserver->execute($this->observerMock);
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

        $this->orderRepositoryMock->expects(self::never())
            ->method('save')
        ;

        $this->setOrderSentToPurseObserver->execute($this->observerMock);
    }
}
