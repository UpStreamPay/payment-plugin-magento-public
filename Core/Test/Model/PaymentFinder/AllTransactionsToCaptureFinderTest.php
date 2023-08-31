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

namespace UpStreamPay\Core\Test\Model\PaymentFinder;

use Generator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\FloatComparator;
use PHPUnit\Framework\MockObject\MockObject;
use UpStreamPay\Core\Exception\NotEnoughFundException;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\PaymentFinder\AllTransactionsFinder;
use UpStreamPay\Core\Model\PaymentFinder\AllTransactionsToCaptureFinder;
use PHPUnit\Framework\TestCase;

/**
 * Class AllTransactionsToCaptureFinderTest
 *
 * @package UpStreamPay\Core\Test\Model\PaymentFinder
 */
class AllTransactionsToCaptureFinderTest extends TestCase
{
    private OrderTransactions&MockObject $orderTransactionsMock;
    private AllTransactionsFinder&MockObject $allTransactionsFinderMock;
    private FloatComparator&MockObject $floatComparator;
    private AllTransactionsToCaptureFinder $allTransactionsToCaptureFinder;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->orderTransactionsMock = self::createMock(OrderTransactions::class);
        $this->allTransactionsFinderMock = self::createMock(AllTransactionsFinder::class);
        $this->floatComparator = self::createMock(FloatComparator::class);

        $this->allTransactionsToCaptureFinder = new AllTransactionsToCaptureFinder(
            $this->orderTransactionsMock,
            $this->allTransactionsFinderMock,
            $this->floatComparator,
        );
    }

    /**
     * @dataProvider captureTransactionsDataProvider
     *
     * @param array $captureTransactionsData
     *
     * @return void
     * @throws LocalizedException
     * @throws NotEnoughFundException
     */
    public function testExecuteCaptureTransactions(
        array $captureTransactionsData,
    ): void
    {
        $this->allTransactionsFinderMock->expects(self::atLeastOnce())
            ->method('execute')
            ->willReturnOnConsecutiveCalls($this->createTransactions($captureTransactionsData), [])
        ;

        $this->floatComparator->expects(self::atLeastOnce())
            ->method('equal')
            ->willReturnOnConsecutiveCalls(false, false, false)
        ;

        $this->orderTransactionsMock->method('getAmountCapturedOnChildCapturesTransaction')
            ->willReturn(0.00)
        ;

        $this->orderTransactionsMock->method('getAmountLeftToCaptureOnTransaction')
            ->willReturn(25.00)
        ;

        $childCaptureMock = self::createMock(OrderTransactions::class);
        $childCaptureMock->expects(self::atMost(1))
            ->method('getAmount')
            ->willReturn(25.00)
        ;
        $this->orderTransactionsMock->method('createChildCaptureTransaction')
            ->willReturn($childCaptureMock)
        ;

        $transactions = $this->allTransactionsToCaptureFinder->execute(25.00, 123, 123);

        foreach ($transactions as $transaction) {
            self::assertSame(25.00, $transaction['amountToCapture']);
        }
    }

    /**
     * @return Generator
     */
    private function captureTransactionsDataProvider(): Generator
    {
        yield 'Two capture transaction not linked to current invoice' => [
            [
                [
                    'invoiceId' => null,
                    'transactionId' => '123',
                    'amount' => 25.00,
                    'parentPaymentId' => 123,

                ],
                [
                    'invoiceId' => 78456,
                    'transactionId' => '123',
                    'amount' => 25.00,
                    'parentPaymentId' => 123,

                ],
            ]
        ];

        yield 'Two capture transaction linked to current invoice' => [
            [
                [
                    'invoiceId' => null,
                    'transactionId' => '123',
                    'amount' => 25.00,
                    'parentPaymentId' => 123,

                ],
                [
                    'invoiceId' => 123,
                    'transactionId' => '123',
                    'amount' => 25.00,
                    'parentPaymentId' => 123,

                ],
            ]
        ];

        yield 'One capture transaction generating child capture' => [
            [
                [
                    'invoiceId' => null,
                    'transactionId' => '123',
                    'amount' => 50.00,
                    'parentPaymentId' => 123,

                ],
            ]
        ];
    }

    /**
     * @dataProvider authorizeTransactionsDataProvider
     *
     * @param array $authorizeTransactionsData
     *
     * @return void
     * @throws LocalizedException
     * @throws NotEnoughFundException
     */
    public function testExecuteAuthorizeTransactions(
        array $authorizeTransactionsData
    ): void
    {
        $this->allTransactionsFinderMock->expects(self::atLeastOnce())
            ->method('execute')
            ->willReturnOnConsecutiveCalls([], $this->createTransactions($authorizeTransactionsData))
        ;

        $this->floatComparator->expects(self::atLeastOnce())
            ->method('equal')
            ->willReturn(false)
        ;

        $this->orderTransactionsMock->expects(self::atLeastOnce())
            ->method('getAmountUsedOnAuthorizeTransaction')
            ->willReturn(0.00)
        ;

        $this->orderTransactionsMock->expects(self::atLeastOnce())
            ->method('getAmountLeftToCaptureOnTransaction')
            ->willReturn(25.00)
        ;

        $transactions = $this->allTransactionsToCaptureFinder->execute(25.00, 123, 123);

        foreach ($transactions as $transaction) {
            self::assertSame(25.00, $transaction['amountToCapture']);
        }
    }

    /**
     * @return Generator
     */
    private function authorizeTransactionsDataProvider(): Generator
    {
        yield 'One authorize transaction' => [
            [
                [
                    'invoiceId' => null,
                    'transactionId' => '123',
                    'amount' => 25.00,
                    'parentPaymentId' => 123,

                ],
            ]
        ];
    }

    /**
     * @return void
     * @throws LocalizedException
     * @throws NotEnoughFundException
     */
    public function testExecuteException(): void
    {
        $this->allTransactionsFinderMock->expects(self::atLeastOnce())
            ->method('execute')
            ->willReturnOnConsecutiveCalls([], [])
        ;

        $this->expectException(NotEnoughFundException::class);
        $this->allTransactionsToCaptureFinder->execute(25.00, 123, 123);
    }

    /**
     * @param array $transactionsData
     *
     * @return array
     */
    private function createTransactions(array $transactionsData): array
    {
        $transactions = [];

        foreach ($transactionsData as $transactionData) {
            $transactionMock = self::createMock(OrderTransactions::class);

            $transactionMock->expects(self::any())
                ->method('getInvoiceId')
                ->willReturn($transactionData['invoiceId'])
            ;
            $transactionMock->expects(self::any())
                ->method('getTransactionId')
                ->willReturn($transactionData['transactionId'])
            ;
            $transactionMock->expects(self::any())
                ->method('getAmount')
                ->willReturn($transactionData['amount'])
            ;
            $transactionMock->expects(self::any())
                ->method('getParentPaymentId')
                ->willReturn($transactionData['parentPaymentId'])
            ;

            $transactions[] = $transactionMock;
        }

        return $transactions;
    }
}
