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

namespace UpStreamPay\Core\Test\Model;

use Exception;
use Magento\Framework\Event\ManagerInterface;
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
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Exception\AuthorizeErrorException;
use UpStreamPay\Core\Model\Actions\CancelService;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Config\Source\Debug;
use UpStreamPay\Core\Model\NotificationService;
use PHPUnit\Framework\TestCase;
use UpStreamPay\Core\Model\OrderTransactions;

/**
 * Class NotificationServiceTest
 *
 * @package UpStreamPay\Core\Test\Model
 */
class NotificationServiceTest extends TestCase
{
    private Config&MockObject $configMock;
    private OrderTransactionsInterface&MockObject $orderTransactionMock;
    private Processor&MockObject $paymentProcessorMock;
    private NotificationService $notificationService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->configMock = self::createMock(Config::class);
        $this->configMock->method('getDebugMode')
            ->willReturn(Debug::DEBUG_VALUE)
        ;

        $this->paymentProcessorMock = self::createMock(Processor::class);
        $orderMock = self::createMock(Order::class);
        //Because of magic methods.
        $methods = array_merge(
            get_class_methods(Payment::class),
            ['setCreatedInvoice']
        );
        $paymentMock = self::createPartialMock(Payment::class, $methods);
        $orderRepositoryMock = self::createMock(OrderRepositoryInterface::class);
        $methods = array_merge(
            get_class_methods(Invoice::class),
            ['getIsPaid']
        );
        $invoiceMock = self::createPartialMock(Invoice::class, $methods);
        $orderTransactionsRepositoryMock = self::createMock(OrderTransactionsRepositoryInterface::class);
        $this->orderTransactionMock = self::createMock(OrderTransactionsInterface::class);
        $invoiceRepositoryMock = self::createMock(InvoiceRepositoryInterface::class);

        $invoiceMock->method('getOrder')
            ->willReturn($orderMock)
        ;
        $invoiceMock->method('getIsPaid')
            ->willReturn(true)
        ;
        $invoiceMock->method('getState')
            ->willReturn(Invoice::STATE_OPEN)
        ;

        $paymentMock->method('setCreatedInvoice')
            ->with($invoiceMock)
            ->willReturnSelf()
        ;
        $paymentMock->method('getOrder')
            ->willReturn($orderMock)
        ;

        $orderMock->method('getPayment')
            ->willReturn($paymentMock)
        ;
        $orderMock->method('getBaseTotalDue')
            ->willReturn(50.00)
        ;
        $orderMock->method('getStatus')
            ->willReturn(Order::STATE_PAYMENT_REVIEW)
        ;

        $orderRepositoryMock->method('get')
            ->willReturn($orderMock)
        ;

        $this->orderTransactionMock->method('getEntityId')
            ->willReturn(123)
        ;
        $this->orderTransactionMock->method('getOrderId')
            ->willReturn(123)
        ;
        $this->orderTransactionMock->method('getTransactionType')
            ->willReturn(OrderTransactions::CAPTURE_ACTION)
        ;
        $this->orderTransactionMock->method('getMethod')
            ->willReturn('paypal')
        ;
        $this->orderTransactionMock->method('getAmount')
            ->willReturn(50.00)
        ;

        $orderTransactionsRepositoryMock->method('getByTransactionId')
            ->willReturn($this->orderTransactionMock)
        ;

        $invoiceRepositoryMock->method('get')
            ->willReturn($invoiceMock)
        ;

        $this->notificationService = new NotificationService(
            $this->configMock,
            $this->paymentProcessorMock,
            $orderRepositoryMock,
            $orderTransactionsRepositoryMock,
            self::createMock(LoggerInterface::class),
            $invoiceRepositoryMock,
            self::createMock(ManagerInterface::class),
            self::createMock(CancelService::class),
        );
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteAuthorizeActionAuthorize(): void
    {
        $notification = [
            'id' => '123',
            'status' => [
                'state' => OrderTransactions::SUCCESS_STATUS,
                'action' => OrderTransactions::AUTHORIZE_ACTION
            ]
        ];

        $this->configMock->expects(self::once())
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_AUTHORIZE)
        ;

        $this->notificationService->execute($notification);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteAuthorizeActionOrder(): void
    {
        $notification = [
            'id' => '123',
            'status' => [
                'state' => OrderTransactions::SUCCESS_STATUS,
                'action' => OrderTransactions::AUTHORIZE_ACTION
            ]
        ];

        $this->configMock->expects(self::exactly(2))
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_ORDER)
        ;

        $this->notificationService->execute($notification);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteAuthorizeException(): void
    {
        $notification = [
            'id' => '123',
            'status' => [
                'state' => OrderTransactions::SUCCESS_STATUS,
                'action' => OrderTransactions::AUTHORIZE_ACTION
            ]
        ];

        $this->configMock->expects(self::once())
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_AUTHORIZE)
        ;

        $this->paymentProcessorMock->method('authorize')
            ->willThrowException(new AuthorizeErrorException());

        $this->notificationService->execute($notification);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteCaptureActionCapture(): void
    {
        $notification = [
            'id' => '123',
            'status' => [
                'state' => OrderTransactions::SUCCESS_STATUS,
                'action' => OrderTransactions::CAPTURE_ACTION
            ]
        ];

        $this->orderTransactionMock->method('getInvoiceId')
            ->willReturn(123)
        ;

        $this->configMock->expects(self::once())
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_AUTHORIZE_CAPTURE)
        ;

        $this->notificationService->execute($notification);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteCaptureActionOrder(): void
    {
        $notification = [
            'id' => '123',
            'status' => [
                'state' => OrderTransactions::SUCCESS_STATUS,
                'action' => OrderTransactions::CAPTURE_ACTION
            ]
        ];

        $this->orderTransactionMock->method('getInvoiceId')
            ->willReturn(null)
        ;

        $this->configMock->expects(self::once())
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_ORDER)
        ;

        $this->notificationService->execute($notification);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteCaptureActionOrderWithTransactionInError(): void
    {
        $notification = [
            'id' => '123',
            'status' => [
                'state' => OrderTransactions::SUCCESS_STATUS,
                'action' => OrderTransactions::CAPTURE_ACTION
            ]
        ];

        $this->orderTransactionMock->method('getInvoiceId')
            ->willReturn(123)
        ;
        $this->orderTransactionMock->method('getStatus')
            ->willReturn(OrderTransactions::ERROR_STATUS)
        ;

        $this->configMock->expects(self::exactly(2))
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_ORDER)
        ;

        $this->notificationService->execute($notification);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteCaptureActionWithException(): void
    {
        $notification = [
            'id' => '123',
            'status' => [
                'state' => OrderTransactions::SUCCESS_STATUS,
                'action' => OrderTransactions::CAPTURE_ACTION
            ]
        ];

        $this->orderTransactionMock->method('getInvoiceId')
            ->willReturn(123)
        ;

        $this->configMock->expects(self::exactly(2))
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_AUTHORIZE_CAPTURE)
        ;

        $this->paymentProcessorMock->expects(self::once())
            ->method('capture')
            ->willThrowException(new Exception())
        ;

        $this->notificationService->execute($notification);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteRefundAction(): void
    {
        $notification = [
            'id' => '123',
            'status' => [
                'state' => OrderTransactions::SUCCESS_STATUS,
                'action' => OrderTransactions::REFUND_ACTION
            ]
        ];

        $this->orderTransactionMock->expects(self::exactly(3))
            ->method('getStatus')
            ->willReturn(OrderTransactions::ERROR_STATUS)
        ;

        $this->notificationService->execute($notification);
    }
}
