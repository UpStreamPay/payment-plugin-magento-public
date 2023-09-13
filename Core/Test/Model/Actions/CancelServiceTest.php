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

namespace Magento\PhpStan\UpStreamPay\Core\Test\Model\Actions;

use Exception;
use Generator;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Api\Data\OrderPaymentInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Model\Actions\CancelService;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Config\Source\Debug;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\PaymentFinder\AllTransactionsToCancelFinder;

/**
 * Class CancelServiceTest
 *
 * @package Magento\PhpStan\UpStreamPay\Core\Test\Model\Actions
 */
class CancelServiceTest extends TestCase
{
    private ClientInterface&MockObject $clientMock;
    private OrderPaymentRepositoryInterface&MockObject $orderPaymentRepositoryMock;
    private AllTransactionsToCancelFinder&MockObject $allTransactionsToCancelFinderMock;
    private OrderTransactions&MockObject $orderTransactionsMock;
    private OrderManagementInterface&MockObject $orderManagementMock;
    private CancelService $cancelService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->clientMock = self::createMock(ClientInterface::class);
        $this->orderPaymentRepositoryMock = self::createMock(OrderPaymentRepositoryInterface::class);
        $this->allTransactionsToCancelFinderMock = self::createMock(AllTransactionsToCancelFinder::class);
        $this->orderTransactionsMock = self::createMock(OrderTransactions::class);
        $loggerMock = self::createMock(LoggerInterface::class);
        $eventManagerMock = self::createMock(ManagerInterface::class);
        $configMock = self::createMock(Config::class);
        $this->orderManagementMock = self::createMock(OrderManagementInterface::class);

        $configMock->method('getDebugMode')->willReturn(Debug::SIMPLE_VALUE);

        $this->cancelService = new CancelService(
            $this->clientMock,
            $this->orderPaymentRepositoryMock,
            $this->allTransactionsToCancelFinderMock,
            $this->orderTransactionsMock,
            $loggerMock,
            $eventManagerMock,
            $configMock,
            $this->orderManagementMock
        );
    }

    /**
     * @dataProvider captureTransactionToCancelDataProvider
     *
     * @param float $amountRefunded
     * @param float $amount
     * @param bool $refundException
     * @param array $transactionsData
     *
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteCancelCapture(
        float $amountRefunded,
        float $amount,
        bool  $refundException,
        array $transactionsData
    ): void
    {
        $orderMock = self::createMock(Order::class);
        $transactionsToCancel = [];

        $expectedBody = [
            'order' => [
                'amount' => 500.00,
                'currency_code' => 'EUR',
            ],
            'amount' => 0.00,
        ];

        foreach ($transactionsData as $transactionData) {
            $transactionMock = self::createMock(OrderTransactionsInterface::class);

            $transactionMock->expects(self::atLeastOnce())
                ->method('getParentPaymentId')
                ->willReturn($transactionData['parentPaymentId'])
            ;

            $transactionMock->expects(self::atLeastOnce())
                ->method('getTransactionType')
                ->willReturn($transactionData['transactionType'])
            ;

            $transactionMock->expects(self::atLeastOnce())
                ->method('getTransactionId')
                ->willReturn($transactionData['transactionId'])
            ;

            $transactionToCancel = [
                'transaction' => $transactionMock,
                'amountToCancel' => $transactionData['amountToCancel'],
            ];

            $transactionsToCancel[] = $transactionToCancel;
            $expectedBody['amount'] = $transactionData['amountUsedForRefund'];
        }

        $orderMock->expects(self::atLeastOnce())
            ->method('getEntityId')
            ->willReturn(123)
        ;

        $orderMock->expects(self::atLeastOnce())
            ->method('getBaseGrandTotal')
            ->willReturn(500.00)
        ;

        $orderMock->expects(self::atLeastOnce())
            ->method('getGlobalCurrencyCode')
            ->willReturn('EUR')
        ;

        $this->allTransactionsToCancelFinderMock->expects(self::once())
            ->method('execute')
            ->willReturn($transactionsToCancel)
        ;

        if ($refundException) {
            $this->clientMock->expects(self::atLeastOnce())
                ->method('refund')
                ->with('123456789', $expectedBody)
                ->willThrowException(new Exception())
            ;
        } else {
            $this->clientMock->expects(self::atLeastOnce())
                ->method('refund')
                ->with('123456789', $expectedBody)
                ->willReturn(['refundTransactionData'])
            ;

            $this->orderTransactionsMock->expects(self::once())
                ->method('createTransactionFromResponse')
                ->willReturn(self::createMock(OrderTransactionsInterface::class))
            ;
        }

        $orderPaymentMock = self::createMock(OrderPaymentInterface::class);

        $orderPaymentMock->expects(self::atLeastOnce())
            ->method('getAmountRefunded')
            ->willReturn($amountRefunded)
        ;

        $orderPaymentMock->expects(self::atLeastOnce())
            ->method('getAmount')
            ->willReturn($amount)
        ;

        $this->orderPaymentRepositoryMock->expects(self::atLeastOnce())
            ->method('getById')
            ->willReturn($orderPaymentMock)
        ;

        $this->orderManagementMock->expects(self::once())
            ->method('cancel')
            ->with(123)
            ->willReturn(true)
        ;

        $this->cancelService->execute($orderMock);
    }

    /**
     * @return Generator
     */
    public function captureTransactionToCancelDataProvider(): Generator
    {
        yield 'One capture transaction with valid refund amount' => [
            'amountRefunded' => 0.00,
            'amount' => 100.00,
            'refundException' => false,
            [
                [
                    'amountUsedForRefund' => 100.00,
                    'amountToCancel' => 100.00,
                    'transactionId' => '123456789',
                    'parentPaymentId' => 123456789,
                    'transactionType' => OrderTransactions::CAPTURE_ACTION,
                ]
            ]
        ];

        yield 'One capture transaction with valid refund amount & api exception' => [
            'amountRefunded' => 0.00,
            'amount' => 100.00,
            'refundException' => true,
            [
                [
                    'amountUsedForRefund' => 100.00,
                    'amountToCancel' => 100.00,
                    'transactionId' => '123456789',
                    'parentPaymentId' => 123456789,
                    'transactionType' => OrderTransactions::CAPTURE_ACTION,
                ]
            ]
        ];

        yield 'One capture transaction with more than allowed refund amount' => [
            'amountRefunded' => 30.00,
            'amount' => 100.00,
            'refundException' => false,
            [
                [
                    'amountUsedForRefund' => 70.00,
                    //Trying to cancel 100 when the max allowed is 70. The code should detect the error & adapt.
                    'amountToCancel' => 100.00,
                    'transactionId' => '123456789',
                    'parentPaymentId' => 123456789,
                    'transactionType' => OrderTransactions::CAPTURE_ACTION,
                ]
            ]
        ];
    }

    /**
     * @dataProvider authorizeTransactionToCancelDataProvider
     *
     * @param bool $voidException
     * @param array $transactionsData
     *
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteAuthorizeCapture(
        bool  $voidException,
        array $transactionsData
    ): void
    {
        $orderMock = self::createMock(Order::class);
        $transactionsToCancel = [];

        foreach ($transactionsData as $transactionData) {
            $transactionMock = self::createMock(OrderTransactionsInterface::class);

            $transactionMock->expects(self::atLeastOnce())
                ->method('getParentPaymentId')
                ->willReturn($transactionData['parentPaymentId'])
            ;

            $transactionMock->expects(self::atLeastOnce())
                ->method('getTransactionType')
                ->willReturn($transactionData['transactionType'])
            ;

            $transactionMock->expects(self::atLeastOnce())
                ->method('getTransactionId')
                ->willReturn($transactionData['transactionId'])
            ;

            $transactionToCancel = [
                'transaction' => $transactionMock,
                'amountToCancel' => 50.00,
            ];

            $transactionsToCancel[] = $transactionToCancel;
        }

        $orderMock->expects(self::atLeastOnce())
            ->method('getEntityId')
            ->willReturn(123)
        ;

        $orderMock->expects(self::atLeastOnce())
            ->method('getBaseGrandTotal')
            ->willReturn(500.00)
        ;

        $orderMock->expects(self::atLeastOnce())
            ->method('getGlobalCurrencyCode')
            ->willReturn('EUR')
        ;

        $this->allTransactionsToCancelFinderMock->expects(self::once())
            ->method('execute')
            ->willReturn($transactionsToCancel)
        ;

        if ($voidException) {
            $this->clientMock->expects(self::atLeastOnce())
                ->method('void')
                ->willThrowException(new Exception())
            ;
        } else {
            $this->clientMock->expects(self::atLeastOnce())
                ->method('void')
                ->willReturn(['voidTransactionData'])
            ;

            $this->orderTransactionsMock->expects(self::once())
                ->method('createTransactionFromResponse')
                ->willReturn(self::createMock(OrderTransactionsInterface::class))
            ;
        }

        $orderPaymentMock = self::createMock(OrderPaymentInterface::class);

        $this->orderPaymentRepositoryMock->expects(self::atLeastOnce())
            ->method('getById')
            ->willReturn($orderPaymentMock)
        ;

        $this->orderManagementMock->expects(self::once())
            ->method('cancel')
            ->with(123)
            ->willReturn(true)
        ;

        $this->cancelService->execute($orderMock);
    }

    /**
     * @return Generator
     */
    public function authorizeTransactionToCancelDataProvider(): Generator
    {
        yield 'One authorize transaction' => [
            'voidException' => false,
            [
                [
                    'transactionId' => '123456789',
                    'parentPaymentId' => 123456789,
                    'transactionType' => OrderTransactions::AUTHORIZE_ACTION,
                ]
            ]
        ];

        yield 'One authorize transaction with api exception' => [
            'voidException' => true,
            [
                [
                    'transactionId' => '123456789',
                    'parentPaymentId' => 123456789,
                    'transactionType' => OrderTransactions::AUTHORIZE_ACTION,
                ]
            ]
        ];
    }
}
