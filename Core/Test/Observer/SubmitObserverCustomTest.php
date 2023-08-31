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

namespace UpStreamPay\Core\Test\Observer;

use Exception;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment as QuotePayment;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Observer\SubmitObserverCustom;
use PHPUnit\Framework\TestCase;

/**
 * Class SubmitObserverCustomTest
 *
 * @package UpStreamPay\Core\Test\Observer
 */
class SubmitObserverCustomTest extends TestCase
{
    private LoggerInterface&MockObject $loggerMock;
    private Order&MockObject $orderMock;
    private Payment&MockObject $orderPaymentMock;
    private Observer&MockObject $observerMock;
    private OrderSender&MockObject $orderSenderMock;
    private SubmitObserverCustom $submitObserverCustom;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->orderMock = self::createMock(Order::class);
        $this->loggerMock = self::createMock(LoggerInterface::class);
        //Because of magic methods.
        $methods = array_merge(
            get_class_methods(Event::class),
            ['getOrder', 'getQuote']
        );
        $eventMock = self::createPartialMock(Event::class, $methods);
        $quoteMock = self::createMock(Quote::class);
        $this->orderPaymentMock = self::createMock(Payment::class);
        $quotePaymentMock = self::createMock(QuotePayment::class);
        $this->observerMock = self::createMock(Observer::class);
        $this->orderSenderMock = self::createMock(OrderSender::class);

        $quotePaymentMock->method('getOrderPlaceRedirectUrl')
            ->willReturn(false)
        ;

        $this->orderMock->method('getPayment')
            ->willReturn($this->orderPaymentMock)
        ;

        $quoteMock->method('getPayment')
            ->willReturn($quotePaymentMock)
        ;

        $eventMock->method('getOrder')
            ->willReturn($this->orderMock)
        ;
        $eventMock->method('getQuote')
            ->willReturn($quoteMock)
        ;

        $this->observerMock->method('getEvent')
            ->willReturn($eventMock)
        ;

        $this->submitObserverCustom = new SubmitObserverCustom(
            $this->loggerMock,
            $this->orderSenderMock
        );
    }

    /**
     * @return void
     */
    public function testExecuteOrderEmailNotAllowed(): void
    {
        $this->orderMock->expects(self::once())
            ->method('getCanSendNewEmailFlag')
            ->willReturn(false)
        ;

        $this->orderPaymentMock->expects(self::never())
            ->method('getMethod')
        ;

        $this->submitObserverCustom->execute($this->observerMock);
    }

    /**
     * @return void
     */
    public function testExecuteWithUpStreamPayMethod(): void
    {

        $this->orderMock->expects(self::once())
            ->method('getCanSendNewEmailFlag')
            ->willReturn(true)
        ;

        $this->orderPaymentMock->expects(self::once())
            ->method('getMethod')
            ->willReturn(Config::METHOD_CODE_UPSTREAM_PAY)
        ;

        $this->orderSenderMock->expects(self::never())
            ->method('send')
        ;

        $this->submitObserverCustom->execute($this->observerMock);
    }

    /**
     * @return void
     */
    public function testExecuteWithoutUpStreamPayMethod(): void
    {

        $this->orderMock->expects(self::once())
            ->method('getCanSendNewEmailFlag')
            ->willReturn(true)
        ;

        $this->orderPaymentMock->expects(self::once())
            ->method('getMethod')
            ->willReturn('fakeMethod')
        ;

        $this->orderSenderMock->expects(self::once())
            ->method('send')
        ;

        $this->submitObserverCustom->execute($this->observerMock);
    }

    /**
     * @return void
     */
    public function testExecuteException(): void
    {

        $this->orderMock->expects(self::once())
            ->method('getCanSendNewEmailFlag')
            ->willReturn(true)
        ;

        $this->orderPaymentMock->expects(self::once())
            ->method('getMethod')
            ->willReturn('fakeMethod')
        ;

        $this->orderSenderMock->expects(self::once())
            ->method('send')
            ->willThrowException(new Exception())
        ;

        $this->loggerMock->expects(self::once())
            ->method('critical')
        ;

        $this->submitObserverCustom->execute($this->observerMock);
    }
}
