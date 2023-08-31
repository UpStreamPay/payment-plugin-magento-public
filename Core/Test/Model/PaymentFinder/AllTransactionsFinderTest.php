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
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\MockObject\MockObject;
use UpStreamPay\Core\Api\Data\OrderPaymentInterface;
use UpStreamPay\Core\Api\Data\OrderPaymentSearchResultsInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsSearchResultsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Model\PaymentFinder\AllTransactionsFinder;
use PHPUnit\Framework\TestCase;
use UpStreamPay\Core\Model\PaymentMethod;

/**
 * Class AllTransactionsFinderTest
 *
 * @package UpStreamPay\Core\Test\Model\PaymentFinder
 */
class AllTransactionsFinderTest extends TestCase
{
    private OrderPaymentRepositoryInterface&MockObject $orderPaymentRepositoryMock;
    private OrderTransactionsRepositoryInterface&MockObject $orderTransactionsRepositoryMock;
    private SearchCriteriaBuilder&MockObject $searchCriteriaBuilderMock;
    private AllTransactionsFinder $allTransactionsFinder;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->orderPaymentRepositoryMock = self::createMock(OrderPaymentRepositoryInterface::class);
        $this->orderTransactionsRepositoryMock = self::createMock(OrderTransactionsRepositoryInterface::class);
        $this->searchCriteriaBuilderMock = self::createMock(SearchCriteriaBuilder::class);

        $this->allTransactionsFinder = new AllTransactionsFinder(
            $this->orderPaymentRepositoryMock,
            $this->orderTransactionsRepositoryMock,
            $this->searchCriteriaBuilderMock,
        );
    }

    /**
     * @dataProvider dataProvider
     *
     * @param null|int $invoiceId
     * @param array $paymentData
     *
     * @return void
     * @throws LocalizedException
     */
    public function testExecute(?int $invoiceId, array $paymentData): void
    {
        $this->searchCriteriaBuilderMock->expects(self::atLeastOnce())
            ->method('addFilter')
            ->willReturnSelf()
        ;

        $searchCriteriaMock = self::createMock(SearchCriteria::class);

        $this->searchCriteriaBuilderMock->expects(self::atLeastOnce())
            ->method('create')
            ->willReturn($searchCriteriaMock)
        ;

        $orderPaymentSearchResultsMock = self::createMock(OrderPaymentSearchResultsInterface::class);

        $this->orderPaymentRepositoryMock->expects(self::atLeastOnce())
            ->method('getList')
            ->with($searchCriteriaMock)
            ->willReturn($orderPaymentSearchResultsMock);

        $orderPaymentSearchResultsMock->expects(self::atLeastOnce())
            ->method('getItems')
            ->willReturn($this->createPayments($paymentData))
        ;

        $orderTransactionsSearchResultsMock = self::createMock(OrderTransactionsSearchResultsInterface::class);

        $this->orderTransactionsRepositoryMock->expects(self::atLeastOnce())
            ->method('getList')
            ->with($searchCriteriaMock)
            ->willReturn($orderTransactionsSearchResultsMock);

        $orderTransactionsSearchResultsMock->expects(self::atLeastOnce())
            ->method('getItems')
            ->willReturnOnConsecutiveCalls(['secondary_transaction'], ['primary_transaction'])
        ;

        $transaction = $this->allTransactionsFinder->execute('capture', 123, 'SUCCESS', $invoiceId);
        self::assertSame(['secondary_transaction', 'primary_transaction'], $transaction);
    }

    /**
     * @param array $paymentsData
     *
     * @return array
     */
    private function createPayments(array $paymentsData): array
    {
        $payments = [];

        foreach ($paymentsData as $paymentData) {
            $paymentMock = self::createMock(OrderPaymentInterface::class);

            $paymentMock->expects(self::atLeastOnce())
                ->method('getType')
                ->willReturn($paymentData['type'])
            ;

            $paymentMock->expects(self::atLeastOnce())
                ->method('getEntityId')
                ->willReturn($paymentData['entityId'])
            ;

            $payments[] = $paymentMock;
        }

        return $payments;
    }

    /**
     * @return Generator
     */
    public function dataProvider(): Generator
    {
        yield 'Invoice ID is null with one primary payment' => [
            'invoiceId' => null,
            [
                [
                    'type' => PaymentMethod::PRIMARY,
                    'entityId' => 123
                ]
            ]
        ];

        yield 'Invoice ID is not null with one secondary payment' => [
            'invoiceId' => 123,
            [
                [
                    'type' => PaymentMethod::SECONDARY,
                    'entityId' => 123
                ]
            ]
        ];

        yield 'Invoice ID is not null with one secondary & one primary payment' => [
            'invoiceId' => 123,
            [
                [
                    'type' => PaymentMethod::SECONDARY,
                    'entityId' => 123
                ],
                [
                    'type' => PaymentMethod::PRIMARY,
                    'entityId' => 456
                ]
            ]
        ];
    }
}
