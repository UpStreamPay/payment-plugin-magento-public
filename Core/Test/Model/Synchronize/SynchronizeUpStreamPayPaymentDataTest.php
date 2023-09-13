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

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Api\Data\PaymentMethodInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Api\PaymentMethodRepositoryInterface;
use UpStreamPay\Core\Exception\NoPaymentMethodFoundException;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Config\Source\Debug;
use UpStreamPay\Core\Model\OrderPayment;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\PaymentMethod;
use UpStreamPay\Core\Model\Synchronize\SynchronizeUpStreamPayPaymentData;
use PHPUnit\Framework\TestCase;

/**
 * Class SynchronizeUpStreamPayPaymentDataTest
 *
 * @package UpStreamPay\Core\Test\Model\Synchronize
 */
class SynchronizeUpStreamPayPaymentDataTest extends TestCase
{
    private SynchronizeUpStreamPayPaymentData $synchronizeUpStreamPayPaymentData;
    private PaymentMethodInterface&MockObject $paymentMethodMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $orderPaymentMock = self::createMock(OrderPayment::class);
        $orderPaymentMock->method('createPaymentFromResponse')
            ->willReturnSelf()
        ;

        $orderTransactionsMock = self::createMock(OrderTransactions::class);

        $orderPaymentRepositoryMock = self::createMock(OrderPaymentRepositoryInterface::class);
        $orderPaymentRepositoryMock->method('getByDefaultTransactionId')
            ->willReturn($orderPaymentMock)
        ;

        $orderTransactionRepositoryMock = self::createMock(OrderTransactionsRepositoryInterface::class);
        $orderTransactionRepositoryMock->method('getByTransactionId')
            ->willReturn($orderTransactionsMock)
        ;

        $this->paymentMethodMock = self::createMock(PaymentMethodInterface::class);

        $paymentMethodRepositoryMock = self::createMock(PaymentMethodRepositoryInterface::class);
        $paymentMethodRepositoryMock->method('getByMethod')
            ->willReturn($this->paymentMethodMock)
        ;

        $configMock = self::createMock(Config::class);
        $configMock->method('getDebugMode')
            ->willReturn(Debug::DEBUG_VALUE)
        ;

        $this->synchronizeUpStreamPayPaymentData = new SynchronizeUpStreamPayPaymentData(
            $orderPaymentMock,
            $orderTransactionsMock,
            $orderPaymentRepositoryMock,
            $orderTransactionRepositoryMock,
            $paymentMethodRepositoryMock,
            $configMock,
            self::createMock(LoggerInterface::class),
        );
    }

    /**
     * @return void
     * @throws LocalizedException
     * @throws NoPaymentMethodFoundException
     */
    public function testExecute(): void
    {
        $this->paymentMethodMock->method('getEntityId')
            ->willReturn(123)
        ;
        $this->paymentMethodMock->method('getType')
            ->willReturn(PaymentMethod::PRIMARY)
        ;

        $expectedOrderTransactionsResponse = [
            'id' => '123',
            'transaction_id' => '456',
            'partner' => 'illicado',
            'method' => 'giftcard'
        ];

        $this->synchronizeUpStreamPayPaymentData->execute([$expectedOrderTransactionsResponse], 123, 123, 123);
    }

    /**
     * @return void
     * @throws LocalizedException
     * @throws NoPaymentMethodFoundException
     */
    public function testExecuteException(): void
    {
        $expectedOrderTransactionsResponse = [
            'id' => '123',
            'transaction_id' => '456',
            'partner' => 'illicado',
            'method' => 'giftcard'
        ];

        $this->expectException(NoPaymentMethodFoundException::class);

        $this->synchronizeUpStreamPayPaymentData->execute([$expectedOrderTransactionsResponse], 123, 123, 123);
    }
}
