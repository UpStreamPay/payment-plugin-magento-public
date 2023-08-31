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
use PHPUnit\Framework\MockObject\MockObject;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\PaymentFinder\AllTransactionsFinder;
use UpStreamPay\Core\Model\PaymentFinder\AllTransactionsToRefundFinder;
use PHPUnit\Framework\TestCase;

/**
 * Class AllTransactionsToRefundFinderTest
 *
 * @package UpStreamPay\Core\Test\Model\PaymentFinder
 */
class AllTransactionsToRefundFinderTest extends TestCase
{
    private AllTransactionsFinder&MockObject $allTransactionsFinderMock;
    private OrderTransactions&MockObject $orderTransactionsMock;
    private AllTransactionsToRefundFinder $allTransactionsToRefundFinder;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->allTransactionsFinderMock = self::createMock(AllTransactionsFinder::class);
        $this->orderTransactionsMock = self::createMock(OrderTransactions::class);

        $this->allTransactionsToRefundFinder = new AllTransactionsToRefundFinder(
            $this->allTransactionsFinderMock,
            $this->orderTransactionsMock,
        );
    }

    /**
     * @dataProvider transactionsDataProvider
     *
     * @param array $captureTransaction
     * @param array $childCaptureTransaction
     *
     * @return void
     * @throws LocalizedException
     */
    public function testExecute(array $captureTransaction, array $childCaptureTransaction): void
    {
        $this->allTransactionsFinderMock->expects(self::atLeastOnce())
            ->method('execute')
            ->willReturnOnConsecutiveCalls(
                $this->createTransactions($captureTransaction),
                $this->createTransactions($childCaptureTransaction)
            )
        ;

        $refundTransactionMock = self::createMock(OrderTransactions::class);
        $refundTransactionMock->expects(self::atLeastOnce())
            ->method('getAmount')
            ->willReturn(10.00)
        ;

        $this->orderTransactionsMock->expects(self::atLeastOnce())
            ->method('getRefundTransactionsFromCapture')
            ->willReturn([$refundTransactionMock])
        ;

        $parentTransactionMock = self::createMock(OrderTransactions::class);
        $parentTransactionMock->method('getAmount')
            ->willReturn(100.00)
        ;

        $this->orderTransactionsMock->method('getParentCaptureFromChildCapture')
            ->willReturn($parentTransactionMock)
        ;

        $transactions = $this->allTransactionsToRefundFinder->execute(123, 123);

        foreach ($transactions as $transaction) {
            self::assertSame(50.00, $transaction['amountToRefund']);
        }
    }

    /**
     * @return Generator
     */
    private function transactionsDataProvider(): Generator
    {
        yield 'Transactions' => [
            'Capture transaction' => [
                'Capture transaction to refund' => [
                    'transactionType' => OrderTransactions::CAPTURE_ACTION,
                    'transactionId' => '123',
                    'parentTransactionId' => null,
                    'amount' => 60.00,
                ],
                'Capture transaction to not refund because the amount is < to the amount already refunded' => [
                    'transactionType' => OrderTransactions::CAPTURE_ACTION,
                    'transactionId' => '123',
                    'parentTransactionId' => null,
                    'amount' => 10.00,
                ]
            ],
            'Child capture transaction' => [
                [
                    'transactionType' => OrderTransactions::CHILD_CAPTURE_TYPE,
                    'transactionId' => '123',
                    'parentTransactionId' => '1234',
                    'amount' => 50.00,
                ]
            ]
        ];
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
            $transactionMock = self::createMock(OrderTransactionsInterface::class);

            $transactionMock->expects(self::any())
                ->method('getTransactionType')
                ->willReturn($transactionData['transactionType'])
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
                ->method('getParentTransactionId')
                ->willReturn($transactionData['parentTransactionId'])
            ;

            $transactions[] = $transactionMock;
        }

        return $transactions;
    }
}
