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

use Generator;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\FloatComparator;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsSearchResultsInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Exception\AuthorizeErrorException;
use UpStreamPay\Core\Model\Actions\AuthorizeService;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\OrderTransactions;

/**
 * Class AuthorizeServiceTest
 *
 * @package Magento\PhpStan\UpStreamPay\Core\Test\Model\Actions
 */
class AuthorizeServiceTest extends TestCase
{
    private const ORDER_ENTITY_ID = 123;

    private AuthorizeService $authorizeService;
    private SearchCriteriaBuilder&MockObject $searchCriteriaBuilderMock;
    private OrderTransactionsRepositoryInterface&MockObject $orderTransactionsRepositoryMock;
    private Config&MockObject $configMock;
    private FloatComparator&MockObject $floatComparatorMock;
    private InfoInterface&MockObject $paymentMock;
    private SearchCriteria&MockObject $searchCriteriaMock;

    protected function setUp(): void
    {
        //Because of magic methods.
        $methods = array_merge(
            get_class_methods(Payment::class),
            ['setIsTransactionApproved', 'setCurrencyCode']
        );

        $this->searchCriteriaBuilderMock = self::createMock(SearchCriteriaBuilder::class);
        $this->orderTransactionsRepositoryMock = self::createMock(OrderTransactionsRepositoryInterface::class);
        $this->configMock = self::createMock(Config::class);
        $this->floatComparatorMock = self::createMock(FloatComparator::class);
        $this->paymentMock = $this->createPartialMock(Payment::class, $methods);
        $this->searchCriteriaMock = self::createMock(SearchCriteria::class);

        $this->authorizeService = new AuthorizeService(
            $this->searchCriteriaBuilderMock,
            $this->orderTransactionsRepositoryMock,
            $this->configMock,
            $this->floatComparatorMock
        );
    }

    /**
     * @dataProvider authorizeTransactionsDataProvider
     *
     * @param bool $hasError
     * @param bool $isWaiting
     * @param string $paymentAction
     * @param array $authorizeTransactionsData
     *
     * @return void
     * @throws AuthorizeErrorException
     * @throws LocalizedException
     */
    public function testExecuteSuccessfulAuthorization(
        bool   $hasError,
        bool   $isWaiting,
        string $paymentAction,
        array  $authorizeTransactionsData
    ): void
    {
        $amount = 0;
        $authorizeTransactions = [];

        foreach ($authorizeTransactionsData as $authorizeTransaction) {
            $amount += $authorizeTransaction['amount'];
            $authorizeTransactionMock = self::createMock(OrderTransactions::class);

            $authorizeTransactionMock->expects(self::any())
                ->method('getStatus')
                ->willReturn($authorizeTransaction['status'])
            ;

            $authorizeTransactionMock->expects(self::any())
                ->method('getSessionId')
                ->willReturn($authorizeTransaction['sessionId'])
            ;

            $authorizeTransactionMock->expects(self::any())
                ->method('getAmount')
                ->willReturn($authorizeTransaction['amount'])
            ;

            $authorizeTransactionMock->expects(self::any())
                ->method('getTransactionType')
                ->willReturn($authorizeTransaction['transactionType'])
            ;

            $authorizeTransactionMock->expects(self::any())
                ->method('getTransactionId')
                ->willReturn($authorizeTransaction['transactionId'])
            ;

            $authorizeTransactionMock->expects(self::any())
                ->method('getMethod')
                ->willReturn($authorizeTransaction['method'])
            ;

            $authorizeTransactions[] = $authorizeTransactionMock;
        }

        $this->configMock->expects(self::once())
            ->method('getPaymentAction')
            ->willReturn($paymentAction)
        ;

        $this->searchCriteriaBuilderMock->expects(self::exactly(2))
            ->method('addFilter')
            ->withConsecutive(
                [OrderTransactionsInterface::TRANSACTION_TYPE, OrderTransactions::AUTHORIZE_ACTION],
                [OrderTransactionsInterface::ORDER_ID, self::ORDER_ENTITY_ID]
            )
            ->willReturnSelf()
        ;

        $this->searchCriteriaBuilderMock->expects(self::once())
            ->method('create')
            ->willReturn($this->searchCriteriaMock)
        ;

        $orderTransactionsSearchResultsMock = self::createMock(OrderTransactionsSearchResultsInterface::class);

        $this->orderTransactionsRepositoryMock->expects(self::once())
            ->method('getList')
            ->with($this->searchCriteriaMock)
            ->willReturn($orderTransactionsSearchResultsMock);

        $orderTransactionsSearchResultsMock->expects(self::once())
            ->method('getItems')
            ->willReturn($authorizeTransactions)
        ;

        $this->floatComparatorMock->expects(self::atMost(1))
            ->method('equal')
            ->willReturn($isWaiting ? false : true)
        ;

        $paymentOrderMock = self::createMock(Order::class);
        $paymentOrderMock->expects(self::once())
            ->method('getEntityId')
            ->willReturn(self::ORDER_ENTITY_ID)
        ;

        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getOrder')
            ->willReturn($paymentOrderMock)
        ;

        if ($hasError) {
            self::expectException(AuthorizeErrorException::class);
        } else {
            $this->paymentMock->expects(self::atMost(1))
                ->method('setTransactionId')
                ->with('fakeSessionId')
                ->willReturnSelf()
            ;

            $this->paymentMock->expects(self::atMost(1))
                ->method('setIsTransactionClosed')
                ->with(false)
                ->willReturnSelf()
            ;

            $this->paymentMock->expects(self::atMost(1))
                ->method('setIsTransactionApproved')
                ->with(true)
                ->willReturnSelf()
            ;

            $this->paymentMock->expects(self::atMost(1))
                ->method('setCurrencyCode')
                ->willReturnSelf()
            ;

            $this->paymentMock->expects(self::atMost(1))
                ->method('setIsTransactionPending')
                ->with($isWaiting)
                ->willReturnSelf()
            ;
        }

        $this->authorizeService->execute($this->paymentMock, $amount);
    }

    /**
     * @return Generator
     */
    public function authorizeTransactionsDataProvider(): Generator
    {
        yield 'One authorize transaction in success' => [
            'hasError' => false,
            'isWaiting' => false,
            'paymentAction' => MethodInterface::ACTION_AUTHORIZE,
            [
                [
                    'sessionId' => 'fakeSessionId',
                    'status' => OrderTransactions::SUCCESS_STATUS,
                    'amount' => 100.00,
                    'transactionType' => OrderTransactions::AUTHORIZE_ACTION,
                    'transactionId' => '123456789',
                    'method' => 'paypal',
                ],
            ]
        ];

        yield 'At least one authorize transaction in error' => [
            'hasError' => true,
            'isWaiting' => false,
            'paymentAction' => MethodInterface::ACTION_AUTHORIZE,
            [
                [
                    'sessionId' => 'fakeSessionId',
                    'status' => OrderTransactions::SUCCESS_STATUS,
                    'amount' => 100.00,
                    'transactionType' => OrderTransactions::AUTHORIZE_ACTION,
                    'transactionId' => '123456789',
                    'method' => 'paypal',
                ],
                [
                    'sessionId' => 'fakeSessionId',
                    'status' => OrderTransactions::ERROR_STATUS,
                    'amount' => 59.94,
                    'transactionType' => OrderTransactions::AUTHORIZE_ACTION,
                    'transactionId' => '1234687',
                    'method' => 'illicado',
                ],
            ]
        ];

        yield 'At least one authorize transaction in waiting' => [
            'hasError' => false,
            'isWaiting' => true,
            'paymentAction' => MethodInterface::ACTION_AUTHORIZE,
            [
                [
                    'sessionId' => 'fakeSessionId',
                    'status' => OrderTransactions::SUCCESS_STATUS,
                    'amount' => 100.00,
                    'transactionType' => OrderTransactions::AUTHORIZE_ACTION,
                    'transactionId' => '123456789',
                    'method' => 'paypal',
                ],
                [
                    'sessionId' => 'fakeSessionId',
                    'status' => OrderTransactions::WAITING_STATUS,
                    'amount' => 59.94,
                    'transactionType' => OrderTransactions::AUTHORIZE_ACTION,
                    'transactionId' => '1234687',
                    'method' => 'illicado',
                ],
            ]
        ];

        yield 'At least two authorize transactions in success' => [
            'hasError' => false,
            'isWaiting' => false,
            'paymentAction' => MethodInterface::ACTION_AUTHORIZE,
            [
                [
                    'sessionId' => 'fakeSessionId',
                    'status' => OrderTransactions::SUCCESS_STATUS,
                    'amount' => 100.00,
                    'transactionType' => OrderTransactions::AUTHORIZE_ACTION,
                    'transactionId' => '123456789',
                    'method' => 'paypal',
                ],
                [
                    'sessionId' => 'fakeSessionId',
                    'status' => OrderTransactions::SUCCESS_STATUS,
                    'amount' => 59.94,
                    'transactionType' => OrderTransactions::AUTHORIZE_ACTION,
                    'transactionId' => '1234687',
                    'method' => 'illicado',
                ],
            ]
        ];

        yield 'No transactions & order action used' => [
            'hasError' => false,
            'isWaiting' => false,
            'paymentAction' => MethodInterface::ACTION_ORDER,
            []
        ];
    }
}
