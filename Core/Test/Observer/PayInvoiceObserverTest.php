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
use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Processor;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Model\Actions\CancelService;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Observer\PayInvoiceObserver;
use PHPUnit\Framework\TestCase;

/**
 * Class PayInvoiceObserverTest
 *
 * @package UpStreamPay\Core\Test\Observer
 */
class PayInvoiceObserverTest extends TestCase
{
    private Config&MockObject $configMock;
    private Observer&MockObject $observerMock;
    private Payment&MockObject $paymentMock;
    private Invoice&MockObject $invoiceMock;
    private PayInvoiceObserver $payInvoiceObserver;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->configMock = self::createMock(Config::class);
        $transactionRepositoryMock = self::createMock(OrderTransactionsRepositoryInterface::class);
        $this->observerMock = self::createMock(Observer::class);
        $orderMock = self::createMock(Order::class);
        //Because of magic methods.
        $methods = array_merge(
            get_class_methods(Payment::class),
            ['setCreatedInvoice']
        );
        $this->paymentMock = self::createPartialMock(Payment::class, $methods);
        //Because of magic methods.
        $methods = array_merge(
            get_class_methods(Invoice::class),
            ['getIsPaid']
        );
        $this->invoiceMock = self::createPartialMock(Invoice::class, $methods);

        $transactionRepositoryMock->method('getByInvoiceId')
            ->willReturn([])
        ;

        $this->observerMock->method('getData')
            ->willReturn($this->invoiceMock)
        ;

        $this->invoiceMock->method('getOrder')
            ->willReturn($orderMock)
        ;
        $this->invoiceMock->method('getIsPaid')
            ->willReturnOnConsecutiveCalls(false, true)
        ;
        $this->invoiceMock->method('getState')
            ->willReturn(Invoice::STATE_OPEN)
        ;

        $orderMock->method('getPayment')
            ->willReturn($this->paymentMock)
        ;

        $this->paymentMock->method('setCreatedInvoice')
            ->willReturn($this->invoiceMock)
        ;

        $this->payInvoiceObserver = new PayInvoiceObserver(
            $this->configMock,
            self::createMock(Processor::class),
            self::createMock(OrderRepositoryInterface::class),
            self::createMock(InvoiceRepositoryInterface::class),
            $transactionRepositoryMock,
            self::createMock(LoggerInterface::class),
            self::createMock(CancelService::class)
        );
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testExecutePaymentMethodNotUpStreamPay(): void
    {
        $this->paymentMock->expects(self::once())
            ->method('getMethod')
            ->willReturn('fakePaymentMethod')
        ;

        $this->configMock->expects(self::never())
            ->method('getPaymentAction')
        ;

        $this->payInvoiceObserver->execute($this->observerMock);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteInvoicePaid(): void
    {
        $this->paymentMock->expects(self::once())
            ->method('getMethod')
            ->willReturn(Config::METHOD_CODE_UPSTREAM_PAY)
        ;

        $this->configMock->expects(self::once())
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_ORDER)
        ;

        $this->invoiceMock->expects(self::once())
            ->method('pay')
        ;

        $this->payInvoiceObserver->execute($this->observerMock);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteException(): void
    {
        $this->paymentMock->expects(self::once())
            ->method('getMethod')
            ->willReturn(Config::METHOD_CODE_UPSTREAM_PAY)
        ;

        $this->configMock->expects(self::once())
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_ORDER)
        ;

        $this->invoiceMock->expects(self::once())
            ->method('pay')
            ->willThrowException(new Exception())
        ;

        $this->invoiceMock->expects(self::once())
            ->method('cancel')
        ;

        $this->payInvoiceObserver->execute($this->observerMock);
    }
}
