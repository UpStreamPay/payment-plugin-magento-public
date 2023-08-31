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
use Magento\Sales\Model\Order\Email\Container\InvoiceIdentity;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Observer\SendUpStreamPayInvoiceEmailObserver;
use PHPUnit\Framework\TestCase;

/**
 * Class SendUpStreamPayInvoiceEmailObserverTest
 *
 * @package UpStreamPay\Core\Test\Observer
 */
class SendUpStreamPayInvoiceEmailObserverTest extends TestCase
{
    private InvoiceSender&MockObject $invoiceSenderMock;
    private InvoiceIdentity&MockObject $invoiceIdentityMock;
    private SendUpStreamPayInvoiceEmailObserver $sendUpStreamPayInvoiceEmailObserver;
    private LoggerInterface&MockObject $loggerMock;
    private Order&MockObject $orderMock;
    private Collection&MockObject $invoiceCollectionMock;
    private Payment&MockObject $orderPaymentMock;
    private Observer&MockObject $observerMock;
    private Invoice&MockObject $invoiceMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->invoiceSenderMock = self::createMock(InvoiceSender::class);
        $this->invoiceIdentityMock = self::createMock(InvoiceIdentity::class);
        $this->orderMock = self::createMock(Order::class);
        $this->loggerMock = self::createMock(LoggerInterface::class);
        $this->invoiceCollectionMock = self::createMock(Collection::class);
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
        $this->invoiceMock = self::createMock(Invoice::class);

        $quotePaymentMock->method('getOrderPlaceRedirectUrl')
            ->willReturn(false)
        ;

        $this->orderMock->method('getInvoiceCollection')
            ->willReturn($this->invoiceCollectionMock)
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

        $this->sendUpStreamPayInvoiceEmailObserver = new SendUpStreamPayInvoiceEmailObserver(
            $this->loggerMock,
            $this->invoiceSenderMock,
            $this->invoiceIdentityMock
        );
    }

    /**
     * @return void
     */
    public function testExecuteInvoiceEmailNotAllowed(): void
    {
        $this->invoiceIdentityMock->method('isEnabled')
            ->willReturn(false)
        ;

        $this->orderMock->expects(self::never())
            ->method('getCanSendNewEmailFlag')
        ;

        $this->sendUpStreamPayInvoiceEmailObserver->execute($this->observerMock);
    }

    /**
     * @return void
     */
    public function testExecuteOrderEmailNotAllowed(): void
    {
        $this->invoiceIdentityMock->method('isEnabled')
            ->willReturn(true)
        ;

        $this->orderMock->expects(self::once())
            ->method('getCanSendNewEmailFlag')
            ->willReturn(false)
        ;

        $this->invoiceCollectionMock->expects(self::never())
            ->method('getItems')
        ;

        $this->sendUpStreamPayInvoiceEmailObserver->execute($this->observerMock);
    }

    /**
     * @return void
     */
    public function testExecuteNoInvoice(): void
    {
        $this->invoiceIdentityMock->method('isEnabled')
            ->willReturn(true)
        ;

        $this->orderMock->expects(self::once())
            ->method('getCanSendNewEmailFlag')
            ->willReturn(true)
        ;

        $this->invoiceCollectionMock->expects(self::once())
            ->method('getItems')
            ->willReturn([])
        ;

        $this->orderPaymentMock->expects(self::never())
            ->method('getMethod')
        ;

        $this->sendUpStreamPayInvoiceEmailObserver->execute($this->observerMock);
    }

    /**
     * @return void
     */
    public function testExecuteWithUpStreamPayMethod(): void
    {
        $this->invoiceIdentityMock->method('isEnabled')
            ->willReturn(true)
        ;

        $this->orderMock->expects(self::once())
            ->method('getCanSendNewEmailFlag')
            ->willReturn(true)
        ;

        $this->invoiceCollectionMock->expects(self::once())
            ->method('getItems')
            ->willReturn([$this->invoiceMock])
        ;

        $this->orderPaymentMock->expects(self::once())
            ->method('getMethod')
            ->willReturn(Config::METHOD_CODE_UPSTREAM_PAY)
        ;

        $this->invoiceSenderMock->expects(self::never())
            ->method('send')
        ;

        $this->sendUpStreamPayInvoiceEmailObserver->execute($this->observerMock);
    }

    /**
     * @return void
     */
    public function testExecuteWithoutUpStreamPayMethod(): void
    {
        $this->invoiceIdentityMock->method('isEnabled')
            ->willReturn(true)
        ;

        $this->orderMock->expects(self::once())
            ->method('getCanSendNewEmailFlag')
            ->willReturn(true)
        ;

        $this->invoiceCollectionMock->expects(self::once())
            ->method('getItems')
            ->willReturn([$this->invoiceMock])
        ;

        $this->orderPaymentMock->expects(self::once())
            ->method('getMethod')
            ->willReturn('fakeMethod')
        ;

        $this->invoiceSenderMock->expects(self::once())
            ->method('send')
            ->willReturn(true)
        ;

        $this->sendUpStreamPayInvoiceEmailObserver->execute($this->observerMock);
    }

    /**
     * @return void
     */
    public function testExecuteException(): void
    {
        $this->invoiceIdentityMock->method('isEnabled')
            ->willReturn(true)
        ;

        $this->orderMock->expects(self::once())
            ->method('getCanSendNewEmailFlag')
            ->willReturn(true)
        ;

        $this->invoiceCollectionMock->expects(self::once())
            ->method('getItems')
            ->willReturn([$this->invoiceMock])
        ;

        $this->orderPaymentMock->expects(self::once())
            ->method('getMethod')
            ->willReturn('fakeMethod')
        ;

        $this->invoiceSenderMock->expects(self::once())
            ->method('send')
            ->willThrowException(new Exception())
        ;

        $this->loggerMock->expects(self::once())
            ->method('critical')
        ;

        $this->sendUpStreamPayInvoiceEmailObserver->execute($this->observerMock);
    }
}
