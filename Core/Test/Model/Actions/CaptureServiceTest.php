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
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\FloatComparator;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use UpStreamPay\Core\Api\Data\OrderPaymentInterface;
use UpStreamPay\Core\Api\Data\OrderPaymentSearchResultsInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsSearchResultsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Exception\CaptureErrorException;
use UpstreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Actions\CaptureService;
use UpStreamPay\Core\Model\OrderTransactions;

/**
 * Class CaptureServiceTest
 *
 * @package Magento\PhpStan\UpStreamPay\Core\Test\Model\Actions
 */
class CaptureServiceTest extends TestCase
{
    private SearchCriteriaBuilder&MockObject $searchCriteriaBuilderMock;
    private OrderTransactionsRepositoryInterface&MockObject $orderTransactionsRepositoryMock;
    private OrderPaymentRepositoryInterface&MockObject $orderPaymentRepositoryMock;
    private Config&MockObject $configMock;
    private FloatComparator&MockObject $floatComparatorMock;
    private Payment&MockObject $paymentMock;
    private SearchCriteria&MockObject $searchCriteriaMock;
    private CaptureService $captureService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        //Because of magic methods.
        $methods = array_merge(
            get_class_methods(Payment::class),
            ['getCreatedInvoice', 'setIsTransactionApproved', 'setCurrencyCode', 'getIsTransactionApproved']
        );

        $this->searchCriteriaBuilderMock = self::createMock(SearchCriteriaBuilder::class);
        $this->orderTransactionsRepositoryMock = self::createMock(OrderTransactionsRepositoryInterface::class);
        $this->orderPaymentRepositoryMock = self::createMock(OrderPaymentRepositoryInterface::class);
        $this->configMock = self::createMock(Config::class);
        $eventManagerMock = self::createMock(EventManager::class);
        $this->floatComparatorMock = self::createMock(FloatComparator::class);
        $this->paymentMock = self::createPartialMock(Payment::class, $methods);
        $this->searchCriteriaMock = self::createMock(SearchCriteria::class);

        $this->captureService = new CaptureService(
            $this->searchCriteriaBuilderMock,
            $this->orderTransactionsRepositoryMock,
            $this->orderPaymentRepositoryMock,
            $this->configMock,
            $eventManagerMock,
            $this->floatComparatorMock
        );
    }

    /**
     * @return void
     * @throws CaptureErrorException
     * @throws LocalizedException
     */
    public function testExecuteWithNoCaptureTransactionAndOrderActionMode(): void
    {
        $this->configMock->expects(self::once())
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_ORDER)
        ;

        $this->searchCriteriaBuilderMock->expects(self::exactly(2))
            ->method('addFilter')
            ->withConsecutive(
                [OrderTransactionsInterface::TRANSACTION_TYPE, OrderTransactions::CAPTURE_ACTION],
                [OrderTransactionsInterface::ORDER_ID, 123]
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
            ->willReturn($orderTransactionsSearchResultsMock)
        ;

        $orderTransactionsSearchResultsMock->expects(self::once())
            ->method('getItems')
            ->willReturn([])
        ;

        $paymentOrderMock = self::createMock(Order::class);
        $paymentOrderMock->expects(self::once())
            ->method('getEntityId')
            ->willReturn(123)
        ;

        //By default, it is set to true before any capture process is done. Because with the provided data it should not
        //be able to capture, it should stay pending.
        $this->paymentMock->method('getIsTransactionPending')
            ->willReturn(true)
        ;

        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getOrder')
            ->willReturn($paymentOrderMock)
        ;

        $payment = $this->captureService->execute($this->paymentMock, 80.00);

        self::assertTrue($payment->getIsTransactionPending());
    }

    /**
     * @dataProvider captureTransactionsDataProvider
     *
     * @param bool $captureException
     * @param bool $isWaiting
     * @param array $captureTransactionsData
     *
     * @return void
     * @throws CaptureErrorException
     * @throws LocalizedException
     */
    public function testExecuteCaptureWithOrderMode(bool $captureException, bool $isWaiting, array $captureTransactionsData): void
    {
        $this->configMock->expects(self::exactly(2))
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_ORDER)
        ;

        $this->searchCriteriaBuilderMock->expects(self::exactly(2))
            ->method('addFilter')
            ->withConsecutive(
                [OrderTransactionsInterface::TRANSACTION_TYPE, OrderTransactions::CAPTURE_ACTION],
                [OrderTransactionsInterface::ORDER_ID, 123]
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
            ->willReturn($orderTransactionsSearchResultsMock)
        ;

        $orderTransactionsSearchResultsMock->expects(self::once())
            ->method('getItems')
            ->willReturn($this->createCaptureTransactions($captureTransactionsData))
        ;

        $invoiceMock = self::createMock(InvoiceInterface::class);

        $invoiceMock->expects(self::atLeastOnce())
            ->method('getEntityId')
            ->willReturn('123')
        ;

        $this->paymentMock->expects(self::atLeast(2))
            ->method('getCreatedInvoice')
            ->willReturn($invoiceMock)
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

        $this->paymentMock->expects(self::atMost(1))
            ->method('setTransactionId')
            ->with('fakeSessionId')
            ->willReturnSelf()
        ;

        if ($captureException) {
            self::expectException(CaptureErrorException::class);
        } elseif (!$isWaiting) {
            $this->floatComparatorMock->expects(self::once())
                ->method('equal')
                ->with(50.00, 50.00)
                ->willReturn(true)
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
                ->with(false)
                ->willReturnSelf()
            ;

            $this->paymentMock->method('getIsTransactionPending')
                ->willReturn(false)
            ;

            $this->paymentMock->method('getIsTransactionApproved')
                ->willReturn(true)
            ;

            $this->paymentMock->method('getTransactionId')
                ->willReturn('fakeSessionId')
            ;
        } else {
            $this->paymentMock->expects(self::atMost(1))
                ->method('setIsTransactionPending')
                ->with(true)
                ->willReturnSelf()
            ;

            $this->paymentMock->method('getIsTransactionPending')
                ->willReturn(true)
            ;
        }

        $payment = $this->captureService->execute($this->paymentMock, 50.00);

        if (!$captureException && !$isWaiting) {
            self::assertTrue($payment->getIsTransactionApproved());
            self::assertFalse($payment->getIsTransactionPending());
            self::assertSame('fakeSessionId', $payment->getTransactionId());
        } elseif ($isWaiting) {
            self::assertTrue($payment->getIsTransactionPending());
        }
    }

    /**
     * @dataProvider captureTransactionsAndPaymentDataProvider
     *
     * @param array $captureTransactionsData
     * @param array $paymentsData
     *
     * @return void
     * @throws CaptureErrorException
     * @throws LocalizedException
     */
    public function testExecuteCaptureWithCaptureMode(array $captureTransactionsData, array $paymentsData): void
    {
        $this->configMock->expects(self::exactly(2))
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_AUTHORIZE_CAPTURE)
        ;

        $this->searchCriteriaBuilderMock->expects(self::exactly(3))
            ->method('addFilter')
            ->withConsecutive(
                [OrderTransactionsInterface::TRANSACTION_TYPE, OrderTransactions::CAPTURE_ACTION],
                [OrderTransactionsInterface::ORDER_ID, 123],
                [OrderPaymentInterface::ENTITY_ID, [1234], 'in']
            )
            ->willReturnSelf()
        ;

        $this->searchCriteriaBuilderMock->expects(self::exactly(2))
            ->method('create')
            ->willReturnOnConsecutiveCalls($this->searchCriteriaMock, $this->searchCriteriaMock)
        ;

        $orderTransactionsSearchResultsMock = self::createMock(OrderTransactionsSearchResultsInterface::class);
        $orderPaymentSearchResultsMock = self::createMock(OrderPaymentSearchResultsInterface::class);

        $this->orderTransactionsRepositoryMock->expects(self::once())
            ->method('getList')
            ->with($this->searchCriteriaMock)
            ->willReturn($orderTransactionsSearchResultsMock)
        ;

        $this->orderPaymentRepositoryMock->expects(self::once())
            ->method('getList')
            ->with($this->searchCriteriaMock)
            ->willReturn($orderPaymentSearchResultsMock)
        ;

        $orderTransactionsSearchResultsMock->expects(self::once())
            ->method('getItems')
            ->willReturn($this->createCaptureTransactions($captureTransactionsData))
        ;

        $orderPaymentSearchResultsMock->expects(self::once())
            ->method('getItems')
            ->willReturn($this->createPayments($paymentsData))
        ;

        $this->paymentMock->expects(self::once())
            ->method('getCreatedInvoice')
            ->willReturn(null)
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

        $this->paymentMock->expects(self::atMost(1))
            ->method('setTransactionId')
            ->with('fakeSessionId')
            ->willReturnSelf()
        ;

        $this->captureService->execute($this->paymentMock, 25.00);
    }

    /**
     * @return Generator
     */
    public function captureTransactionsDataProvider(): Generator
    {
        yield 'One capture transaction in success' => [
            'captureException' => false,
            'isWaiting' => false,
            [
                [
                    'sessionId' => 'fakeSessionId',
                    'status' => OrderTransactions::SUCCESS_STATUS,
                    'amount' => 50.00,
                    'parentPaymentId' => 1234,
                ]
            ]
        ];

        yield 'One capture transaction in success & one in error' => [
            'captureException' => true,
            'isWaiting' => false,
            [
                [
                    'sessionId' => 'fakeSessionId',
                    'status' => OrderTransactions::SUCCESS_STATUS,
                    'amount' => 25.00,
                    'parentPaymentId' => 1234,
                ],
                [
                    'sessionId' => 'fakeSessionId',
                    'status' => OrderTransactions::ERROR_STATUS,
                    'amount' => 25.00,
                    'parentPaymentId' => 1234,
                ]
            ]
        ];

        yield 'One capture transaction in success & one in waiting' => [
            'captureException' => false,
            'isWaiting' => true,
            [
                [
                    'sessionId' => 'fakeSessionId',
                    'status' => OrderTransactions::SUCCESS_STATUS,
                    'amount' => 25.00,
                    'parentPaymentId' => 1234,
                ],
                [
                    'sessionId' => 'fakeSessionId',
                    'status' => OrderTransactions::WAITING_STATUS,
                    'amount' => 25.00,
                    'parentPaymentId' => 1234,
                ]
            ]
        ];
    }

    /**
     * @return Generator
     */
    public function captureTransactionsAndPaymentDataProvider(): Generator
    {
        yield 'One capture transaction in success with payment' => [
            'Capture transactions' => [
                [
                    'sessionId' => 'fakeSessionId',
                    'status' => OrderTransactions::SUCCESS_STATUS,
                    'amount' => 25.00,
                    'parentPaymentId' => 1234,
                ]
            ],
            'Payments' => [
                [
                    'amountCaptured' => 25.00,
                    'amount' => 50.00,
                    'entityId' => 1234,
                ]
            ]
        ];
    }

    /**
     * @param array $captureTransactionsData
     *
     * @return array
     */
    private function createCaptureTransactions(array $captureTransactionsData): array
    {
        $captureTransactions = [];

        foreach ($captureTransactionsData as $captureTransactionData) {
            $captureTransactionMock = self::createMock(OrderTransactionsInterface::class);

            $captureTransactionMock->method('getSessionId')
                ->willReturn($captureTransactionData['sessionId'])
            ;

            $captureTransactionMock->method('getStatus')
                ->willReturn($captureTransactionData['status'])

            ;

            $captureTransactionMock->method('getAmount')
                ->willReturn($captureTransactionData['amount'])

            ;

            $captureTransactionMock->method('getParentPaymentId')
                ->willReturn($captureTransactionData['parentPaymentId'])

            ;

            $captureTransactions[] = $captureTransactionMock;
        }

        return $captureTransactions;
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

            $paymentMock->method('getAmountCaptured')
                ->willReturn($paymentData['amountCaptured'])
            ;

            $paymentMock->method('setAmountCaptured')
                ->with(50.00)
                ->willReturnSelf()
            ;

            $paymentMock->method('getAmount')
                ->willReturn($paymentData['amount'])
            ;

            $paymentMock->method('getEntityId')
                ->willReturn($paymentData['entityId'])
            ;

            $payments[] = $paymentMock;
        }

        return $payments;
    }
}
