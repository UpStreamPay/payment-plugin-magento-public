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
            ['setIsTransactionApproved']
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

    public function testExecuteWithDifferentAmountProcessed(): void
    {

    }

    public function testExecuteWithTransactions(): void
    {

    }

    private function transactionsDataProvider()
    {

    }
}
