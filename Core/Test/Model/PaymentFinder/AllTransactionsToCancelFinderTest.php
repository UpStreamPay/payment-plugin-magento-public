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
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Model\Order\Invoice;
use PHPUnit\Framework\MockObject\MockObject;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\PaymentFinder\AllTransactionsFinder;
use UpStreamPay\Core\Model\PaymentFinder\AllTransactionsToCancelFinder;
use PHPUnit\Framework\TestCase;

/**
 * Class AllTransactionsToCancelFinderTest
 *
 * @package UpStreamPay\Core\Test\Model\PaymentFinder
 */
class AllTransactionsToCancelFinderTest extends TestCase
{
    private AllTransactionsFinder&MockObject $allTransactionsFinderMock;
    private OrderTransactions&MockObject $orderTransactionsMock;
    private InvoiceRepositoryInterface&MockObject $invoiceRepositoryMock;
    private FloatComparator&MockObject $floatComparatorMock;
    private AllTransactionsToCancelFinder $allTransactionsToCancelFinder;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->allTransactionsFinderMock = self::createMock(AllTransactionsFinder::class);
        $this->orderTransactionsMock = self::createMock(OrderTransactions::class);
        $this->invoiceRepositoryMock = self::createMock(InvoiceRepositoryInterface::class);
        $this->floatComparatorMock = self::createMock(FloatComparator::class);

        $this->allTransactionsToCancelFinder = new AllTransactionsToCancelFinder(
            $this->allTransactionsFinderMock,
            $this->orderTransactionsMock,
            $this->invoiceRepositoryMock,
            $this->floatComparatorMock,
        );
    }

    /**
     * @dataProvider captureTransactionsDataProvider
     *
     * @param array $transactionsData
     * @param array $childTransactionsData
     *
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteCapture(array $transactionsData, array $childTransactionsData): void
    {
        $this->allTransactionsFinderMock->expects(self::atLeastOnce())
            ->method('execute')
            ->willReturnOnConsecutiveCalls($this->createTransactions($transactionsData), [])
        ;

        $invoiceMock = self::createMock(InvoiceInterface::class);
        $invoiceMock->expects(self::any())
            ->method('getState')
            ->willReturn(Invoice::STATE_PAID)
        ;
        $this->invoiceRepositoryMock->expects(self::any())
            ->method('get')
            ->willReturn($invoiceMock)
        ;

        $refundTransactionMock = self::createMock(OrderTransactionsInterface::class);
        $refundTransactionMock->expects(self::any())
            ->method('getAmount')
            ->willReturn(10.00)
        ;

        $this->orderTransactionsMock->expects(self::any())
            ->method('getRefundTransactionsFromCapture')
            ->willReturn([$refundTransactionMock])
        ;

        $this->floatComparatorMock->expects(self::any())
            ->method('equal')
            ->willReturn(false)
        ;

        $this->orderTransactionsMock->expects(self::any())
            ->method('getChildCapturesTransactionsFromCapture')
            ->willReturn($this->createTransactions($childTransactionsData))
        ;

        $this->floatComparatorMock->expects(self::any())
            ->method('greaterThan')
            ->willReturn(false)
        ;

        $transactions = $this->allTransactionsToCancelFinder->execute(123);

        foreach ($transactions as $transaction) {
            self::assertSame(25.00, $transaction['amountToCancel']);
        }
    }

    /**
     * @dataProvider authorizeTransactionsDataProvider
     *
     * @param array $transactionsData
     * @param array $voidTransactionsData
     * @param bool $equalAmount
     *
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteAuthorize(
        array $transactionsData,
        array $voidTransactionsData,
        bool $equalAmount = false
    ): void
    {
        $this->allTransactionsFinderMock->expects(self::atLeastOnce())
            ->method('execute')
            ->willReturnOnConsecutiveCalls([], $this->createTransactions($transactionsData))
        ;

        $this->orderTransactionsMock->expects(self::any())
            ->method('getVoidTransactionsFromAuthorize')
            ->willReturn($this->createTransactions($voidTransactionsData))
        ;

        $this->floatComparatorMock->expects(self::any())
            ->method('equal')
            ->willReturn($equalAmount)
        ;

        $this->orderTransactionsMock->expects(self::any())
            ->method('getCaptureTransactionsFromAuthorize')
            ->willReturn([])
        ;

        $this->floatComparatorMock->expects(self::any())
            ->method('greaterThan')
            ->willReturn(false)
        ;

        $transactions = $this->allTransactionsToCancelFinder->execute(123);

        foreach ($transactions as $transaction) {
            self::assertSame(25.00, $transaction['amountToCancel']);
        }
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

            $transactions[] = $transactionMock;
        }

        return $transactions;
    }

    /**
     * @return Generator
     */
    public function captureTransactionsDataProvider(): Generator
    {
        yield 'One capture transaction linked to an invoice' => [
            'Capture transaction' => [
                [
                    'invoiceId' => 123,
                    'transactionId' => '123',
                    'amount' => 50.00,
                ]
            ],
            'Child capture' => [
            ]
        ];

        yield 'One capture transaction linked to no invoice & child capture linked to invoice' => [
            'Capture transaction' => [
                [
                    'invoiceId' => null,
                    'transactionId' => '456',
                    'amount' => 48.97,
                ]
            ],
            'Child capture' => [
                [
                    'invoiceId' => 123,
                    'transactionId' => '789',
                    'amount' => 23.97,
                ]
            ],
        ];

        yield 'Two capture transaction linked to no invoice' => [
            'Capture transaction' => [
                [
                    'invoiceId' => null,
                    'transactionId' => '101112',
                    'amount' => 25.00,
                ],
                [
                    'invoiceId' => null,
                    'transactionId' => '131415',
                    'amount' => 25.00,
                ]
            ],
            'Child capture' => [
            ]
        ];

        yield 'Two capture transaction linked to an invoice' => [
            'Capture transaction' => [
                [
                    'invoiceId' => 123,
                    'transactionId' => '1611718',
                    'amount' => 25.00,
                ],
                [
                    'invoiceId' => null,
                    'transactionId' => '192021',
                    'amount' => 25.00,
                ]
            ],
            'Child capture' => [
            ]
        ];
    }

    /**
     * @return Generator
     */
    public function authorizeTransactionsDataProvider(): Generator
    {
        yield 'One authorize transaction' => [
            'Authorize transaction' => [
                [
                    //useless on an authorize transaction but keeping for code reusability.
                    'invoiceId' => 123,
                    'transactionId' => '123',
                    'amount' => 25.00,
                ]
            ],
            'Void transaction' => [
            ]
        ];

        yield 'One authorize transaction & void transaction' => [
            'Authorize transaction' => [
                [
                    //useless on an authorize transaction but keeping for code reusability.
                    'invoiceId' => null,
                    'transactionId' => '456',
                    'amount' => 48.97,
                ]
            ],
            'Void transaction' => [
                [
                    //useless on an authorize transaction but keeping for code reusability.
                    'invoiceId' => 123,
                    'transactionId' => '789',
                    'amount' => 48.97,
                ]
            ],
            'equalAmount' => true
        ];

        yield 'Two authorize transaction' => [
            'Authorize transaction' => [
                [
                    //useless on an authorize transaction but keeping for code reusability.
                    'invoiceId' => null,
                    'transactionId' => '101112',
                    'amount' => 25.00,
                ],
                [
                    //useless on an authorize transaction but keeping for code reusability.
                    'invoiceId' => null,
                    'transactionId' => '131415',
                    'amount' => 25.00,
                ]
            ],
            'Void transaction' => [
            ]
        ];
    }
}
