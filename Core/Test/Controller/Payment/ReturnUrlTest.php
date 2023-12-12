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

namespace UpStreamPay\Core\Test\Controller\Payment;

use Exception;
use Generator;
use Magento\Checkout\Model\Session;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Event\Manager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Payment;
use Magento\Sales\Model\Order\Payment\Processor;
use Magento\Sales\Model\ResourceModel\Order\Invoice\Collection as InvoiceCollection;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Controller\Payment\ReturnUrl;
use UpStreamPay\Core\Exception\AuthorizeErrorException;
use UpStreamPay\Core\Model\Config;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class ReturnUrlTest
 *
 * @package UpStreamPay\Core\Test\Controller\Payment
 */
class ReturnUrlTest extends TestCase
{
    private ReturnUrl $controller;
    private MockObject&Session $checkoutSessionMock;
    private MockObject&RedirectFactory $redirectFactoryMock;
    private MockObject&Config $configMock;
    private MockObject&OrderRepositoryInterface $orderRepositoryMock;
    private MockObject&Processor $paymentProcessorMock;
    private MockObject&InvoiceRepositoryInterface $invoiceRepositoryMock;
    private MockObject&ManagerInterface $messageManager;
    private MockObject&OrderSender $orderSenderMock;
    private MockObject&InvoiceSender $invoiceSenderMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->checkoutSessionMock = $this->createMock(Session::class);
        $this->redirectFactoryMock = $this->createMock(RedirectFactory::class);
        $loggerMock = $this->createMock(LoggerInterface::class);
        $this->configMock = $this->createMock(Config::class);
        $this->orderRepositoryMock = $this->createMock(OrderRepositoryInterface::class);
        $this->paymentProcessorMock = $this->createMock(Processor::class);
        $this->invoiceRepositoryMock = $this->createMock(InvoiceRepositoryInterface::class);
        $this->messageManager = $this->createMock(ManagerInterface::class);
        $this->orderSenderMock = $this->createMock(OrderSender::class);
        $this->invoiceSenderMock = $this->createMock(InvoiceSender::class);
        $eventManagerMock = self::createMock(Manager::class);

        $this->controller = new ReturnUrl(
            $this->checkoutSessionMock,
            $this->redirectFactoryMock,
            $loggerMock,
            $this->configMock,
            $this->orderRepositoryMock,
            $this->paymentProcessorMock,
            $this->invoiceRepositoryMock,
            $this->messageManager,
            $this->orderSenderMock,
            $this->invoiceSenderMock,
            $eventManagerMock
        );
    }

    /**
     * @dataProvider paymentActionProvider
     *
     * @param string $action
     * @param string $method
     *
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteAction(string $action, string $method): void
    {
        $resultRedirectMock = self::createMock(Redirect::class);
        $orderMock = self::createMock(Order::class);
        $paymentMock = self::createMock(Payment::class);

        $orderMock->expects(self::once())
            ->method('getPayment')
            ->willReturn($paymentMock)
        ;

        //Because the capture action requires a few extra mock.
        if ($action === MethodInterface::ACTION_AUTHORIZE_CAPTURE) {
            //Because getIsPaid is set using the DataObject logic & doesn't work in phpunit.
            $invoiceMock = self::createPartialMock(Invoice::class, ['getIsPaid', 'pay']);
            $invoiceCollectionMock = self::createMock(InvoiceCollection::class);

            $invoiceMock->expects(self::once())
                ->method('getIsPaid')
                ->willReturn(true)
            ;

            $invoiceMock->expects(self::once())
                ->method('pay')
                ->willReturn($invoiceMock)
            ;

            $invoiceCollectionMock->expects(self::once())
                ->method('getFirstItem')
                ->willReturn($invoiceMock)
            ;

            $orderMock->expects(self::once())
                ->method('getInvoiceCollection')
                ->willReturn($invoiceCollectionMock)
            ;

            $this->invoiceRepositoryMock->expects(self::once())
                ->method('save')
                ->with($invoiceMock)
            ;

            $this->invoiceSenderMock->expects(self::once())
                ->method('send')
                ->with($invoiceMock)
            ;
        }

        $this->paymentProcessorMock->expects(self::once())
            ->method($method)
            ->willReturn($paymentMock)
        ;

        $this->checkoutSessionMock->expects(self::once())
            ->method('getLastRealOrder')
            ->willReturn($orderMock);

        $this->configMock->expects(self::atMost(3))
            ->method('getPaymentAction')
            ->willReturn($action);

        $this->orderRepositoryMock->expects(self::once())
            ->method('save')
            ->with($orderMock);

        $this->orderSenderMock->expects(self::once())
            ->method('send')
            ->with($orderMock);

        $this->redirectFactoryMock->expects(self::once())
            ->method('create')
            ->willReturn($resultRedirectMock);

        $resultRedirectMock->expects(self::once())
            ->method('setPath')
            ->with('checkout/onepage/success', ['_secure' => true]);

        self::assertEquals($resultRedirectMock, $this->controller->execute());
    }

    /**
     * @return Generator
     */
    public function paymentActionProvider(): Generator
    {
        yield 'Authorize action' => [
            'action' => MethodInterface::ACTION_AUTHORIZE,
            'method' => 'authorize',
        ];

        yield 'Capture action' => [
            'action' => MethodInterface::ACTION_AUTHORIZE_CAPTURE,
            'method' => 'capture',
        ];

        yield 'Order action' => [
            'action' => MethodInterface::ACTION_ORDER,
            'method' => 'order'
        ];
    }

    /**
     * @dataProvider exceptionDataProvider
     *
     * @param string $expectedExceptionClass
     * @param bool $paymentDenyException
     *
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteActionException(string $expectedExceptionClass, bool $paymentDenyException): void
    {
        $resultRedirectMock = self::createMock(Redirect::class);
        $orderMock = self::createMock(Order::class);
        $paymentMock = self::createMock(Payment::class);

        $this->checkoutSessionMock->expects(self::once())
            ->method('getLastRealOrder')
            ->willReturn($orderMock)
        ;

        $orderMock->expects(self::once())
            ->method('getPayment')
            ->willReturn($paymentMock)
        ;

        $this->configMock->expects(self::once())
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_AUTHORIZE);

        $this->configMock->expects(self::once())
            ->method('getErrorMessage')
            ->willReturn('Error message')
        ;

        $this->paymentProcessorMock->expects(self::once())
            ->method('authorize')
            ->willThrowException(new $expectedExceptionClass())
        ;

        if ($paymentDenyException) {
            $paymentMock->expects(self::once())
                ->method('deny')
                ->willThrowException(new $expectedExceptionClass)
            ;
        } else {
            $paymentMock->expects(self::once())
                ->method('deny')
                ->willReturn($paymentMock)
            ;
        }

        $this->orderRepositoryMock->expects(self::once())
            ->method('save')
            ->with($orderMock)
        ;

        $this->checkoutSessionMock->expects(self::atMost(1))
            ->method('restoreQuote')
            ->willReturn(true)
        ;

        $this->messageManager->expects(self::once())
            ->method('addErrorMessage')
            ->with('Error message')
        ;

        $this->redirectFactoryMock->expects(self::once())
            ->method('create')
            ->willReturn($resultRedirectMock)
        ;

        $resultRedirectMock->expects(self::once())
            ->method('setPath')
            ->with('checkout/cart', ['_secure' => true])
        ;

        self::assertEquals($resultRedirectMock, $this->controller->execute());
    }

    /**
     * @return Generator
     */
    public function exceptionDataProvider(): Generator
    {
        yield 'Payment action exception' => [
            'expectedExceptionClass' => AuthorizeErrorException::class,
            'paymentDenyError' => false,
        ];

        yield 'Generic exception' => [
            'expectedExceptionClass' => Exception::class,
            'paymentDenyError' => false,
        ];

        yield 'Payment deny exception' => [
            'expectedExceptionClass' => AuthorizeErrorException::class,
            'paymentDenyError' => true,
        ];
    }
}
