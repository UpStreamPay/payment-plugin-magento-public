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

namespace UpStreamPay\Core\Test\Model\Actions;

use Generator;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\FloatComparator;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsSearchResultsInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Exception\AuthorizeErrorException;
use UpStreamPay\Core\Exception\CaptureErrorException;
use UpStreamPay\Core\Exception\OrderErrorException;
use UpStreamPay\Core\Model\Actions\AuthorizeService;
use UpStreamPay\Core\Model\Actions\CaptureService;
use UpStreamPay\Core\Model\Actions\OrderService;
use PHPUnit\Framework\TestCase;
use UpStreamPay\Core\Model\OrderTransactions;

/**
 * Class OrderServiceTest
 *
 * @package UpStreamPay\Core\Test\Model\Actions
 */
class OrderServiceTest extends TestCase
{
    private AuthorizeService&MockObject $authorizeServiceMock;
    private CaptureService&MockObject $captureServiceMock;
    private SearchCriteriaBuilder&MockObject $searchCriteriaBuilderMock;
    private OrderTransactionsRepositoryInterface&MockObject $orderTransactionsRepositoryMock;
    private LoggerInterface&MockObject $loggerMock;
    private FloatComparator&MockObject $floatComparatorMock;
    private SearchCriteria&MockObject $searchCriteriaMock;
    private Payment&MockObject $paymentMock;
    private OrderService $orderService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        //Because of magic methods.
        $methods = array_merge(
            get_class_methods(Payment::class),
            ['setIsTransactionApproved', 'getIsTransactionApproved']
        );

        $this->authorizeServiceMock = self::createMock(AuthorizeService::class);
        $this->captureServiceMock = self::createMock(CaptureService::class);
        $this->searchCriteriaBuilderMock = self::createMock(SearchCriteriaBuilder::class);
        $this->orderTransactionsRepositoryMock = self::createMock(OrderTransactionsRepositoryInterface::class);
        $this->loggerMock = self::createMock(LoggerInterface::class);
        $this->floatComparatorMock = self::createMock(FloatComparator::class);
        $this->searchCriteriaMock = self::createMock(SearchCriteria::class);
        $this->paymentMock = self::createPartialMock(Payment::class, $methods);

        $this->orderService = new OrderService(
            $this->authorizeServiceMock,
            $this->captureServiceMock,
            $this->searchCriteriaBuilderMock,
            $this->orderTransactionsRepositoryMock,
            $this->loggerMock,
            $this->floatComparatorMock,
        );
    }

    /**
     * @return void
     * @throws LocalizedException
     * @throws AuthorizeErrorException
     * @throws CaptureErrorException
     * @throws OrderErrorException
     */
    public function testExecuteWithNoTransactions(): void
    {
        $this->searchCriteriaBuilderMock->expects(self::exactly(2))
            ->method('addFilter')
            ->withConsecutive(
                [OrderTransactionsInterface::ORDER_ID, 123],
                [OrderTransactionsInterface::TRANSACTION_TYPE, [
                        OrderTransactions::CAPTURE_ACTION,
                        OrderTransactions::AUTHORIZE_ACTION
                    ],
                    'in'
                ]
            )
            ->willReturnSelf()
        ;

        $paymentOrderMock = self::createMock(Order::class);
        $paymentOrderMock->expects(self::once())
            ->method('getEntityId')
            ->willReturn(123)
        ;

        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getOrder')
            ->willReturn($paymentOrderMock)
        ;

        $this->searchCriteriaBuilderMock->expects(self::once())
            ->method('create')
            ->willReturn($this->searchCriteriaMock)
        ;

        $orderTransactionsSearchResultsMock = self::createMock(OrderTransactionsSearchResultsInterface::class);

        $this->orderTransactionsRepositoryMock->expects(self::once())
            ->method('getList')
            ->with($this->searchCriteriaMock)
            ->willReturn($orderTransactionsSearchResultsMock)
        ;

        $orderTransactionsSearchResultsMock->expects(self::once())
            ->method('getItems')
            ->willReturn([])
        ;

        $this->paymentMock->method('getIsTransactionPending')
            ->willReturn(true)
        ;

        $payment = $this->orderService->execute($this->paymentMock, 25.00);
        self::assertTrue($payment->getIsTransactionPending());
    }

    /**
     * @dataProvider transactionsWithWrongAmountDataProvider
     *
     * @param array $transactionsData
     *
     * @return void
     * @throws AuthorizeErrorException
     * @throws CaptureErrorException
     * @throws LocalizedException
     * @throws OrderErrorException
     */
    public function testExecuteWithDifferentAmountProcessed(array $transactionsData): void
    {
        $this->searchCriteriaBuilderMock->expects(self::exactly(2))
            ->method('addFilter')
            ->withConsecutive(
                [OrderTransactionsInterface::ORDER_ID, 123],
                [OrderTransactionsInterface::TRANSACTION_TYPE, [
                    OrderTransactions::CAPTURE_ACTION,
                    OrderTransactions::AUTHORIZE_ACTION
                ],
                    'in'
                ]
            )
            ->willReturnSelf()
        ;

        $paymentOrderMock = self::createMock(Order::class);
        $paymentOrderMock->expects(self::atLeastOnce())
            ->method('getEntityId')
            ->willReturn(123)
        ;

        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getOrder')
            ->willReturn($paymentOrderMock)
        ;

        $this->searchCriteriaBuilderMock->expects(self::once())
            ->method('create')
            ->willReturn($this->searchCriteriaMock)
        ;

        $orderTransactionsSearchResultsMock = self::createMock(OrderTransactionsSearchResultsInterface::class);

        $this->orderTransactionsRepositoryMock->expects(self::once())
            ->method('getList')
            ->with($this->searchCriteriaMock)
            ->willReturn($orderTransactionsSearchResultsMock)
        ;

        $orderTransactionsSearchResultsMock->expects(self::once())
            ->method('getItems')
            ->willReturn($this->createTransactions($transactionsData))
        ;

        $this->floatComparatorMock->expects(self::once())
            ->method('equal')
            ->willReturn(false)
        ;

        self::expectException(OrderErrorException::class);
        $this->orderService->execute($this->paymentMock, 25.00);
    }

    /**
     * @dataProvider transactionsDataProvider
     *
     * @param bool $pendingAuthorize
     * @param bool $pendingCapture
     * @param array $transactionsData
     *
     * @return void
     * @throws AuthorizeErrorException
     * @throws CaptureErrorException
     * @throws LocalizedException
     * @throws OrderErrorException
     */
    public function testExecuteWithTransactions(
        bool $pendingAuthorize,
        bool $pendingCapture,
        array $transactionsData
    ): void
    {
        $this->searchCriteriaBuilderMock->expects(self::exactly(2))
            ->method('addFilter')
            ->withConsecutive(
                [OrderTransactionsInterface::ORDER_ID, 123],
                [OrderTransactionsInterface::TRANSACTION_TYPE, [
                    OrderTransactions::CAPTURE_ACTION,
                    OrderTransactions::AUTHORIZE_ACTION
                ],
                    'in'
                ]
            )
            ->willReturnSelf()
        ;

        $paymentOrderMock = self::createMock(Order::class);
        $paymentOrderMock->expects(self::atLeastOnce())
            ->method('getEntityId')
            ->willReturn(123)
        ;

        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getOrder')
            ->willReturn($paymentOrderMock)
        ;

        $this->searchCriteriaBuilderMock->expects(self::once())
            ->method('create')
            ->willReturn($this->searchCriteriaMock)
        ;

        $orderTransactionsSearchResultsMock = self::createMock(OrderTransactionsSearchResultsInterface::class);

        $this->orderTransactionsRepositoryMock->expects(self::once())
            ->method('getList')
            ->with($this->searchCriteriaMock)
            ->willReturn($orderTransactionsSearchResultsMock)
        ;

        $orderTransactionsSearchResultsMock->expects(self::once())
            ->method('getItems')
            ->willReturn($this->createTransactions($transactionsData))
        ;

        $this->floatComparatorMock->expects(self::once())
            ->method('equal')
            ->willReturn(true)
        ;

        $capturePaymentMock = self::createMock(Payment::class);
        $capturePaymentMock->method('getIsTransactionPending')
            ->willReturn($pendingCapture)
        ;

        $this->authorizeServiceMock->expects(self::atLeastOnce())
            ->method('execute')
            ->willReturn(self::createMock(Payment::class))
        ;

        $this->captureServiceMock->expects(self::atLeastOnce())
            ->method('execute')
            ->willReturn($capturePaymentMock)
        ;

        if ($pendingCapture || $pendingAuthorize) {
            $expectPending = true;
            $this->paymentMock->method('setIsTransactionPending')
                ->with(true)
                ->willReturnSelf()
            ;

            $this->paymentMock->method('setIsTransactionApproved')
                ->with(false)
                ->willReturnSelf()
            ;

            $this->paymentMock->method('getIsTransactionPending')
                ->willReturn(true)
            ;
            $this->paymentMock->method('getIsTransactionApproved')
                ->willReturn(false)
            ;
        } else {
            $expectPending = false;
            $this->paymentMock->method('setIsTransactionPending')
                ->with(false)
                ->willReturnSelf()
            ;

            $this->paymentMock->method('setIsTransactionApproved')
                ->with(true)
                ->willReturnSelf()
            ;

            $this->paymentMock->method('getIsTransactionPending')
                ->willReturn(false)
            ;
            $this->paymentMock->method('getIsTransactionApproved')
                ->willReturn(true)
            ;
        }

        $payment = $this->orderService->execute($this->paymentMock, 30.00);
        self::assertSame($expectPending, $payment->getIsTransactionPending());
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

            $transactionMock->expects(self::atMost(2))
                ->method('getTransactionType')
                ->willReturn($transactionData['transactionType'])
            ;

            $transactionMock->expects(self::once())
                ->method('getAmount')
                ->willReturn($transactionData['amount'])
            ;

            $transactions[] = $transactionMock;
        }

        return $transactions;
    }

    /**
     * Transactions with an amount that will be different that the total amount expected.
     *
     * @return Generator
     */
    private function transactionsWithWrongAmountDataProvider(): Generator
    {
        yield 'One capture transaction with wrong amount' => [
            [
                [
                    'transactionType' => OrderTransactions::CAPTURE_ACTION,
                    'amount' => 12.00
                ]
            ]
        ];

        yield 'One authorize transaction with wrong amount' => [
            [
                [
                    'transactionType' => OrderTransactions::AUTHORIZE_ACTION,
                    'amount' => 12.00
                ]
            ]
        ];

        yield 'Several transaction with wrong amount' => [
            [
                [
                    'transactionType' => OrderTransactions::CAPTURE_ACTION,
                    'amount' => 12.00
                ],
                [
                    'transactionType' => OrderTransactions::AUTHORIZE_ACTION,
                    'amount' => 6.00
                ],
                [
                    'transactionType' => OrderTransactions::CAPTURE_ACTION,
                    'amount' => 8.00
                ]
            ]
        ];
    }

    /**
     * @return Generator
     */
    private function transactionsDataProvider(): Generator
    {
        yield 'One capture transaction not in pending' => [
            'pendingAuthorize' => false,
            'pendingCapture' => false,
            [
                [
                    'transactionType' => OrderTransactions::CAPTURE_ACTION,
                    'amount' => 30.00
                ]
            ]
        ];

        yield 'One authorize transaction not in pending' => [
            'pendingAuthorize' => false,
            'pendingCapture' => false,
            [
                [
                    'transactionType' => OrderTransactions::AUTHORIZE_ACTION,
                    'amount' => 30.00
                ]
            ]
        ];

        yield 'One capture transaction in pending' => [
            'pendingAuthorize' => false,
            'pendingCapture' => true,
            [
                [
                    'transactionType' => OrderTransactions::CAPTURE_ACTION,
                    'amount' => 30.00
                ]
            ]
        ];

        yield 'Several transaction with capture pending' => [
            'pendingAuthorize' => false,
            'pendingCapture' => true,
            [
                [
                    'transactionType' => OrderTransactions::CAPTURE_ACTION,
                    'amount' => 10.00
                ],
                [
                    'transactionType' => OrderTransactions::AUTHORIZE_ACTION,
                    'amount' => 10.00
                ],
                [
                    'transactionType' => OrderTransactions::CAPTURE_ACTION,
                    'amount' => 10.00
                ]
            ]
        ];

        yield 'Several transaction with no pending' => [
            'pendingAuthorize' => false,
            'pendingCapture' => false,
            [
                [
                    'transactionType' => OrderTransactions::CAPTURE_ACTION,
                    'amount' => 10.99
                ],
                [
                    'transactionType' => OrderTransactions::AUTHORIZE_ACTION,
                    'amount' => 09.01
                ],
                [
                    'transactionType' => OrderTransactions::CAPTURE_ACTION,
                    'amount' => 10.00
                ]
            ]
        ];

        yield 'Several transaction with capture & authorize pending' => [
            'pendingAuthorize' => true,
            'pendingCapture' => true,
            [
                [
                    'transactionType' => OrderTransactions::CAPTURE_ACTION,
                    'amount' => 10.00
                ],
                [
                    'transactionType' => OrderTransactions::AUTHORIZE_ACTION,
                    'amount' => 10.00
                ],
                [
                    'transactionType' => OrderTransactions::CAPTURE_ACTION,
                    'amount' => 10.00
                ]
            ]
        ];
    }
}
