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

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\Method\Logger;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use UpStreamPay\Client\Exception\NoSessionFoundException;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\Synchronize\OrderSynchronizeService;
use UpStreamPay\Core\Model\UpStreamPay;
use PHPUnit\Framework\TestCase;

/**
 * Class UpStreamPayTest
 *
 * @package UpStreamPay\Core\Test\Model
 */
class UpStreamPayTest extends TestCase
{
    private OrderSynchronizeService&MockObject $orderSynchronizeServiceMock;
    private Config&MockObject $configMock;
    private Payment&MockObject $paymentMock;
    private UpStreamPay $upStreamPay;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->orderSynchronizeServiceMock = self::createMock(OrderSynchronizeService::class);
        $this->configMock = self::createMock(Config::class);
        $this->paymentMock = self::createMock(Payment::class);

        $orderMock = self::createMock(Order::class);
        $orderMock->method('getData')
            ->willReturn(1)
        ;

        $this->paymentMock->method('getOrder')
            ->willReturn($orderMock)
        ;

        $this->upStreamPay = new UpStreamPay(
            $this->orderSynchronizeServiceMock,
            $this->configMock,
            self::createMock(Context::class),
            self::createMock(Registry::class),
            self::createMock(ExtensionAttributesFactory::class),
            self::createMock(AttributeValueFactory::class),
            self::createMock(Data::class),
            self::createMock(ScopeConfigInterface::class),
            self::createMock(Logger::class),
        );
    }

    /**
     * @return void
     */
    public function testCanAuthorize(): void
    {
        $this->configMock->expects(self::exactly(2))
            ->method('getPaymentAction')
            ->willReturnOnConsecutiveCalls(MethodInterface::ACTION_AUTHORIZE, MethodInterface::ACTION_ORDER)
        ;

        self::assertTrue($this->upStreamPay->canAuthorize());
        self::assertFalse($this->upStreamPay->canAuthorize());
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testAuthorize(): void
    {
        $this->orderSynchronizeServiceMock->expects(self::once())
            ->method('execute')
            ->willReturn($this->paymentMock)
        ;

        $this->upStreamPay->authorize($this->paymentMock, 10.00);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testAuthorizeException(): void
    {
        $this->orderSynchronizeServiceMock->expects(self::once())
            ->method('execute')
            ->willThrowException(new NoSessionFoundException())
        ;

        $this->upStreamPay->authorize($this->paymentMock, 10.00);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testVoid(): void
    {
        $this->configMock->expects(self::once())
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_AUTHORIZE)
        ;

        $this->orderSynchronizeServiceMock->expects(self::once())
            ->method('execute')
            ->with($this->paymentMock, 0.00, OrderTransactions::VOID_ACTION)
            ->willReturn($this->paymentMock)
        ;

        $this->upStreamPay->void($this->paymentMock);
    }

    /**
     * @return void
     */
    public function testCanOrder(): void
    {
        $this->configMock->expects(self::exactly(2))
            ->method('getPaymentAction')
            ->willReturnOnConsecutiveCalls(MethodInterface::ACTION_AUTHORIZE, MethodInterface::ACTION_ORDER)
        ;

        self::assertFalse($this->upStreamPay->canOrder());
        self::assertTrue($this->upStreamPay->canOrder());
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testCapture(): void
    {
        $this->configMock->expects(self::exactly(2))
            ->method('getPaymentAction')
            ->willReturnOnConsecutiveCalls(MethodInterface::ACTION_ORDER, MethodInterface::ACTION_AUTHORIZE_CAPTURE)
        ;

        $this->orderSynchronizeServiceMock->expects(self::exactly(2))
            ->method('execute')
            ->withConsecutive(
                [$this->paymentMock, 10.00, OrderTransactions::ORDER_CAPTURE_ACTION],
                [$this->paymentMock, 30.00, OrderTransactions::CAPTURE_ACTION]
            )
            ->willReturn($this->paymentMock)
        ;

        $this->upStreamPay->capture($this->paymentMock, 10.00);
        $this->upStreamPay->capture($this->paymentMock, 30.00);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testCaptureException(): void
    {
        $this->configMock->expects(self::once())
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_ORDER)
        ;

        $this->orderSynchronizeServiceMock->expects(self::once())
            ->method('execute')
            ->with($this->paymentMock, 10.00, OrderTransactions::ORDER_CAPTURE_ACTION)
            ->willThrowException(new NoSessionFoundException())
        ;

        $this->upStreamPay->capture($this->paymentMock, 10.00);
    }

    /**
     * @return void
     */
    public function testCanCapture(): void
    {
        $this->configMock->expects(self::exactly(2))
            ->method('getPaymentAction')
            ->willReturnOnConsecutiveCalls(MethodInterface::ACTION_AUTHORIZE_CAPTURE, MethodInterface::ACTION_AUTHORIZE)
        ;

        self::assertTrue($this->upStreamPay->canCapture());
        self::assertFalse($this->upStreamPay->canCapture());
    }

    /**
     * @return void
     */
    public function testCancel(): void
    {
        $this->configMock->expects(self::exactly(2))
            ->method('getPaymentAction')
            ->willReturnOnConsecutiveCalls(MethodInterface::ACTION_ORDER, MethodInterface::ACTION_AUTHORIZE_CAPTURE)
        ;

        $this->orderSynchronizeServiceMock->expects(self::exactly(2))
            ->method('execute')
            ->withConsecutive(
                [$this->paymentMock, 00.00, OrderTransactions::ORDER_CANCEL],
                [$this->paymentMock, 00.00, OrderTransactions::VOID_ACTION]
            )
            ->willReturn($this->paymentMock)
        ;

        $this->upStreamPay->cancel($this->paymentMock);
        $this->upStreamPay->cancel($this->paymentMock);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testRefund(): void
    {
        $this->orderSynchronizeServiceMock->expects(self::once())
            ->method('execute')
            ->with($this->paymentMock, 30.00, OrderTransactions::REFUND_ACTION)
            ->willReturn($this->paymentMock)
        ;

        $this->upStreamPay->refund($this->paymentMock, 30.00);
    }

    /**
     * @return void
     */
    public function testGetPaymentAction(): void
    {
        $this->configMock->expects(self::exactly(2))
            ->method('getPaymentAction')
            ->willReturnOnConsecutiveCalls(MethodInterface::ACTION_ORDER, MethodInterface::ACTION_AUTHORIZE_CAPTURE)
        ;

        self::assertSame(MethodInterface::ACTION_ORDER, $this->upStreamPay->getPaymentAction());
        self::assertSame(MethodInterface::ACTION_AUTHORIZE_CAPTURE, $this->upStreamPay->getPaymentAction());
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testOrder(): void
    {
        $this->orderSynchronizeServiceMock->expects(self::once())
            ->method('execute')
            ->with($this->paymentMock, 45.00, OrderTransactions::ORDER_ACTION)
            ->willReturn($this->paymentMock)
        ;

        $this->upStreamPay->order($this->paymentMock, 45.00);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testOrderException(): void
    {
        $this->orderSynchronizeServiceMock->expects(self::once())
            ->method('execute')
            ->with($this->paymentMock, 45.00, OrderTransactions::ORDER_ACTION)
            ->willThrowException(new NoSessionFoundException())
        ;

        $this->upStreamPay->order($this->paymentMock, 45.00);
    }

    /**
     * @return void
     * @throws LocalizedException
     */
    public function testDenyPayment(): void
    {
        $this->configMock->expects(self::exactly(2))
            ->method('getPaymentAction')
            ->willReturnOnConsecutiveCalls(MethodInterface::ACTION_ORDER, MethodInterface::ACTION_AUTHORIZE_CAPTURE)
        ;

        $this->orderSynchronizeServiceMock->expects(self::exactly(2))
            ->method('execute')
            ->withConsecutive(
                [$this->paymentMock, 00.00, OrderTransactions::ORDER_CANCEL],
                [$this->paymentMock, 00.00, OrderTransactions::VOID_ACTION],
            )
            ->willReturn($this->paymentMock)
        ;

        self::assertTrue($this->upStreamPay->denyPayment($this->paymentMock));
        self::assertTrue($this->upStreamPay->denyPayment($this->paymentMock));
    }
}
