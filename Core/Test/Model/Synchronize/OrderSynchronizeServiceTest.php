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

namespace UpStreamPay\Core\Test\Model\Synchronize;

use Generator;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\InfoInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use UpStreamPay\Client\Exception\NoOrderFoundException;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Exception\AuthorizeErrorException;
use UpStreamPay\Core\Exception\CaptureErrorException;
use UpStreamPay\Core\Exception\NoPaymentMethodFoundException;
use UpStreamPay\Core\Exception\NotEnoughFundException;
use UpStreamPay\Core\Exception\NoTransactionsException;
use UpStreamPay\Core\Exception\OrderErrorException;
use UpStreamPay\Core\Model\Actions\AuthorizeService;
use UpStreamPay\Core\Model\Actions\CancelService;
use UpStreamPay\Core\Model\Actions\CaptureService;
use UpStreamPay\Core\Model\Actions\OrderActionCaptureService;
use UpStreamPay\Core\Model\Actions\OrderService;
use UpStreamPay\Core\Model\Actions\RefundService;
use UpStreamPay\Core\Model\Actions\VoidService;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\Synchronize\OrderSynchronizeService;
use PHPUnit\Framework\TestCase;
use UpStreamPay\Core\Model\Synchronize\SynchronizeUpStreamPayPaymentData;

/**
 * Class OrderSynchronizeServiceTest
 *
 * @package UpStreamPay\Core\Test\Model\Synchronize
 */
class OrderSynchronizeServiceTest extends TestCase
{
    private ClientInterface&MockObject $clientMock;
    private Payment&MockObject $paymentMock;
    private OrderSynchronizeService $orderSynchronizeService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $orderMock = self::createMock(Order::class);
        $orderMock->method('getQuoteId')
            ->willReturn('123')
        ;

        $this->clientMock = self::createMock(ClientInterface::class);

        $this->paymentMock = self::createMock(Payment::class);
        $this->paymentMock->method('getOrder')
            ->willReturn($orderMock)
        ;
        $this->paymentMock->method('getParentId')
            ->willReturn('123')
        ;
        $this->paymentMock->method('getEntityId')
            ->willReturn('123')
        ;

        $this->orderSynchronizeService = new OrderSynchronizeService(
            $this->clientMock,
            self::createMock(SynchronizeUpStreamPayPaymentData::class),
            self::createMock(CaptureService::class),
            self::createMock(AuthorizeService::class),
            self::createMock(VoidService::class),
            self::createMock(RefundService::class),
            self::createMock(OrderService::class),
            self::createMock(OrderActionCaptureService::class),
            self::createMock(CancelService::class),
        );
    }

    /**
     * @return void
     * @throws NoTransactionsException
     * @throws GuzzleException
     * @throws JsonException
     * @throws LocalizedException
     * @throws NoOrderFoundException
     * @throws AuthorizeErrorException
     * @throws CaptureErrorException
     * @throws NoPaymentMethodFoundException
     * @throws NotEnoughFundException
     * @throws OrderErrorException
     */
    public function testExecuteException(): void
    {
        $this->clientMock->expects(self::once())
            ->method('getAllTransactionsForOrder')
            ->willReturn([])
        ;

        self::expectException(NoTransactionsException::class);

        $this->orderSynchronizeService->execute($this->paymentMock, 20.00, OrderTransactions::AUTHORIZE_ACTION);
    }

    /**
     * @dataProvider paymentActionDataProvider
     *
     * @param string $paymentAction
     *
     * @return void
     * @throws AuthorizeErrorException
     * @throws CaptureErrorException
     * @throws GuzzleException
     * @throws JsonException
     * @throws LocalizedException
     * @throws NoOrderFoundException
     * @throws NoPaymentMethodFoundException
     * @throws NoTransactionsException
     * @throws NotEnoughFundException
     * @throws OrderErrorException
     */
    public function testExecute(string $paymentAction): void
    {
        $this->clientMock->expects(self::any())
            ->method('getAllTransactionsForOrder')
            ->willReturn(['transaction_data'])
        ;

        $payment = $this->orderSynchronizeService->execute($this->paymentMock, 20.00, $paymentAction);

        self::assertInstanceOf(InfoInterface::class, $payment);
    }

    /**
     * @return Generator
     */
    private function paymentActionDataProvider(): Generator
    {
        yield 'Authorize payment action' => [
            'action' => OrderTransactions::AUTHORIZE_ACTION,
        ];

        yield 'Capture payment action' => [
            'action' => OrderTransactions::CAPTURE_ACTION,
        ];

        yield 'Void payment action' => [
            'action' => OrderTransactions::VOID_ACTION,
        ];

        yield 'Refund payment action' => [
            'action' => OrderTransactions::REFUND_ACTION,
        ];

        yield 'Order action payment action' => [
            'action' => OrderTransactions::ORDER_ACTION,
        ];

        yield 'Order capture payment action' => [
            'action' => OrderTransactions::ORDER_CAPTURE_ACTION,
        ];

        yield 'Order cancel payment action' => [
            'action' => OrderTransactions::ORDER_CANCEL,
        ];

        yield 'Unknown payment action' => [
            'action' => 'foo',
        ];
    }
}
