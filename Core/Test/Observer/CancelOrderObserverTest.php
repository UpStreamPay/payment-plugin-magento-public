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

use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use UpStreamPay\Core\Model\Actions\CancelService;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Observer\CancelOrderObserver;
use PHPUnit\Framework\TestCase;

/**
 * Class CancelOrderObserverTest
 *
 * @package UpStreamPay\Core\Test\Observer
 */
class CancelOrderObserverTest extends TestCase
{
    private Config&MockObject $configMock;
    private Observer&MockObject $observerMock;
    private Payment&MockObject $paymentMock;
    private Order&MockObject $orderMock;
    private Collection&MockObject $invoiceCollectionMock;
    private Invoice&MockObject $invoiceMock;
    private CancelService&MockObject $cancelServiceMock;
    private CancelOrderObserver $cancelOrderObserver;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->configMock = self::createMock(Config::class);
        $this->observerMock = self::createMock(Observer::class);
        $this->orderMock = self::createMock(Order::class);
        $this->paymentMock = self::createMock(Payment::class);
        $this->invoiceCollectionMock = self::createMock(Collection::class);
        $this->invoiceMock = self::createMock(Invoice::class);
        $this->cancelServiceMock = self::createMock(CancelService::class);

        $this->observerMock->method('getData')
            ->willReturn($this->orderMock)
        ;

        $this->orderMock->method('getPayment')
            ->willReturn($this->paymentMock)
        ;

        $this->cancelOrderObserver = new CancelOrderObserver(
            $this->cancelServiceMock,
            $this->configMock,
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

        //Validates that we exit the observer because the payment method is not UpStreamPay.
        $this->orderMock->expects(self::never())
            ->method('getInvoiceCollection')
        ;

        $this->cancelOrderObserver->execute($this->observerMock);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testExecutePaymentActionCapture(): void
    {
        $this->paymentMock->expects(self::once())
            ->method('getMethod')
            ->willReturn(Config::METHOD_CODE_UPSTREAM_PAY)
        ;

        $this->configMock->expects(self::once())
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_AUTHORIZE_CAPTURE)
        ;

        $this->orderMock->expects(self::once())
            ->method('getInvoiceCollection')
            ->willReturn($this->invoiceCollectionMock)
        ;

        $this->paymentMock->expects(self::once())
            ->method('canVoid')
            ->willReturn(false)
        ;

        $this->invoiceMock->expects(self::never())
            ->method('getState')
        ;

        $this->cancelServiceMock->expects(self::never())
            ->method('execute')
        ;

        $this->cancelOrderObserver->execute($this->observerMock);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteAtLeastOnceCanceledInvoice(): void
    {
        $this->paymentMock->expects(self::once())
            ->method('getMethod')
            ->willReturn(Config::METHOD_CODE_UPSTREAM_PAY)
        ;

        $this->configMock->expects(self::once())
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_ORDER)
        ;

        $this->orderMock->expects(self::once())
            ->method('getInvoiceCollection')
            ->willReturn([$this->invoiceMock])
        ;

        $this->paymentMock->expects(self::once())
            ->method('canVoid')
            ->willReturn(false)
        ;

        $this->invoiceMock->expects(self::once())
            ->method('getState')
            ->willReturn(Invoice::STATE_CANCELED)
        ;

        $this->cancelServiceMock->expects(self::once())
            ->method('execute')
        ;

        $this->orderMock->expects(self::never())
            ->method('getBaseTotalDue')
        ;

        $this->cancelOrderObserver->execute($this->observerMock);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteTotalDueLeft(): void
    {
        $this->paymentMock->expects(self::once())
            ->method('getMethod')
            ->willReturn(Config::METHOD_CODE_UPSTREAM_PAY)
        ;

        $this->configMock->expects(self::once())
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_ORDER)
        ;

        $this->orderMock->expects(self::once())
            ->method('getInvoiceCollection')
            ->willReturn([$this->invoiceMock])
        ;

        $this->paymentMock->expects(self::once())
            ->method('canVoid')
            ->willReturn(false)
        ;

        $this->invoiceMock->expects(self::once())
            ->method('getState')
            ->willReturn(Invoice::STATE_PAID)
        ;

        $this->orderMock->expects(self::once())
            ->method('getBaseTotalDue')
            ->willReturn(20.00)
        ;

        $this->cancelServiceMock->expects(self::once())
            ->method('execute')
        ;

        $this->cancelOrderObserver->execute($this->observerMock);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteNoTransactionsToCancel(): void
    {
        $this->paymentMock->expects(self::once())
            ->method('getMethod')
            ->willReturn(Config::METHOD_CODE_UPSTREAM_PAY)
        ;

        $this->configMock->expects(self::never())
            ->method('getPaymentAction')
        ;

        $this->orderMock->expects(self::once())
            ->method('getInvoiceCollection')
            ->willReturn([$this->invoiceMock])
        ;

        $this->paymentMock->expects(self::never())
            ->method('canVoid')
        ;

        $this->invoiceMock->expects(self::once())
            ->method('getState')
            ->willReturn(Invoice::STATE_PAID)
        ;

        $this->orderMock->expects(self::once())
            ->method('getBaseTotalDue')
            ->willReturn(00.00)
        ;

        $this->cancelServiceMock->expects(self::never())
            ->method('execute')
        ;

        $this->cancelOrderObserver->execute($this->observerMock);
    }
}
