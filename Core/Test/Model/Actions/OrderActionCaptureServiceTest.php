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

use Exception;
use Generator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\FloatComparator;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Api\Data\OrderPaymentInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Exception\CaptureErrorException;
use UpStreamPay\Core\Exception\NotEnoughFundException;
use UpStreamPay\Core\Model\Actions\OrderActionCaptureService;
use PHPUnit\Framework\TestCase;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\PaymentFinder\AllTransactionsToCaptureFinder;

/**
 * Class OrderActionCaptureServiceTest
 *
 * @package UpStreamPay\Core\Test\Model\Actions
 */
class OrderActionCaptureServiceTest extends TestCase
{
    private AllTransactionsToCaptureFinder&MockObject $allTransactionsToCaptureFinderMock;
    private OrderPaymentRepositoryInterface&MockObject $orderPaymentRepositoryMock;
    private ClientInterface&MockObject $clientMock;
    private OrderTransactions&MockObject $orderTransactionsMock;
    private FloatComparator&MockObject $floatComparatorMock;
    private OrderActionCaptureService $orderActionCaptureService;
    private Payment&MockObject $paymentMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        //Because of magic methods.
        $methods = array_merge(
            get_class_methods(Payment::class),
            ['getCreatedInvoice', 'setIsTransactionApproved', 'getIsTransactionApproved']
        );

        $this->allTransactionsToCaptureFinderMock = self::createMock(AllTransactionsToCaptureFinder::class);
        $orderTransactionsRepositoryMock = self::createMock(OrderTransactionsRepositoryInterface::class);
        $this->orderPaymentRepositoryMock = self::createMock(OrderPaymentRepositoryInterface::class);
        $this->clientMock = self::createMock(ClientInterface::class);
        $this->orderTransactionsMock = self::createMock(OrderTransactions::class);
        $this->floatComparatorMock = self::createMock(FloatComparator::class);
        $this->paymentMock = self::createPartialMock(Payment::class, $methods);

        $this->orderActionCaptureService = new OrderActionCaptureService(
            $this->allTransactionsToCaptureFinderMock,
            $orderTransactionsRepositoryMock,
            $this->orderPaymentRepositoryMock,
            $this->clientMock,
            $this->orderTransactionsMock,
            $this->floatComparatorMock,
        );
    }

    /**
     * Test with no invoice set.
     *
     * @return void
     * @throws LocalizedException
     * @throws CaptureErrorException
     * @throws NotEnoughFundException
     */
    public function testExecuteWithNullInvoice(): void
    {
        $this->paymentMock->expects(self::once())
            ->method('getCreatedInvoice')
            ->willReturn(null)
        ;

        $this->paymentMock->expects(self::once())
            ->method('setIsTransactionPending')
            ->with(true)
            ->willReturnSelf()
        ;

        $this->paymentMock->method('getIsTransactionPending')
            ->willReturn(true)
        ;

        $payment = $this->orderActionCaptureService->execute($this->paymentMock, 50.00);

        self::assertTrue($payment->getIsTransactionPending());
    }

    /**
     * Test with an exception returned on the finder.
     *
     * @return void
     * @throws CaptureErrorException
     * @throws LocalizedException
     * @throws NotEnoughFundException
     */
    public function testExecuteWithFinderException(): void
    {
        $invoiceMock = self::createMock(InvoiceInterface::class);
        $invoiceMock->expects(self::exactly(2))
            ->method('getEntityId')
            ->willReturn('123')
        ;

        $this->paymentMock->expects(self::exactly(3))
            ->method('getCreatedInvoice')
            ->willReturn($invoiceMock)
        ;

        $paymentOrderMock = self::createMock(Order::class);
        $paymentOrderMock->expects(self::atLeastOnce())
            ->method('getEntityId')
            ->willReturn('123')
        ;

        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getOrder')
            ->willReturn($paymentOrderMock)
        ;

        $this->allTransactionsToCaptureFinderMock->expects(self::once())
            ->method('execute')
            ->willThrowException(new NotEnoughFundException())
        ;

        self::expectException(NotEnoughFundException::class);

        $this->orderActionCaptureService->execute($this->paymentMock, 50.00);
    }

    /**
     * Test with only capture transactions on the order.
     *
     * @dataProvider captureTransactionsDataProvider
     *
     * @param array $transactionsData
     *
     * @return void
     * @throws CaptureErrorException
     * @throws LocalizedException
     * @throws NotEnoughFundException
     */
    public function testExecuteWithCaptureTransactions(array $transactionsData): void
    {
        $invoiceMock = self::createMock(InvoiceInterface::class);
        $invoiceMock->expects(self::exactly(2))
            ->method('getEntityId')
            ->willReturn('123')
        ;

        $this->paymentMock->expects(self::exactly(3))
            ->method('getCreatedInvoice')
            ->willReturn($invoiceMock)
        ;

        $paymentOrderMock = self::createMock(Order::class);
        $paymentOrderMock->expects(self::atLeastOnce())
            ->method('getEntityId')
            ->willReturn('123')
        ;

        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getOrder')
            ->willReturn($paymentOrderMock)
        ;

        $this->allTransactionsToCaptureFinderMock->expects(self::once())
            ->method('execute')
            ->willReturn($this->createTransactions($transactionsData))
        ;

        $orderPaymentMock = self::createMock(OrderPaymentInterface::class);

        $orderPaymentMock->expects(self::once())
            ->method('setAmountCaptured')
            ->with(50.00)
            ->willReturnSelf()
        ;

        $orderPaymentMock->expects(self::once())
            ->method('getAmountCaptured')
            ->willReturn(25.00)
        ;

        $this->orderPaymentRepositoryMock->expects(self::once())
            ->method('getById')
            ->with(123)
            ->willReturn($orderPaymentMock)
        ;

        $this->floatComparatorMock->expects(self::once())
            ->method('equal')
            ->with(25.00, 25.00)
            ->willReturn(true)
        ;

        $this->paymentMock->expects(self::atMost(1))
            ->method('setTransactionId')
            ->with('fakeSessionId-123')
            ->willReturnSelf()
        ;

        $this->paymentMock->expects(self::atMost(1))
            ->method('setIsTransactionClosed')
            ->with(false)
            ->willReturnSelf()
        ;

        $this->paymentMock->expects(self::once())
            ->method('setIsTransactionApproved')
            ->with(true)
            ->willReturnSelf()
        ;

        $this->paymentMock->expects(self::once())
            ->method('getIsTransactionApproved')
            ->willReturn(true)
        ;

        $this->paymentMock->expects(self::once())
            ->method('setIsTransactionPending')
            ->with(false)
            ->willReturnSelf()
        ;

        $this->paymentMock->expects(self::once())
            ->method('getIsTransactionPending')
            ->willReturn(false)
        ;

        $payment = $this->orderActionCaptureService->execute($this->paymentMock, 25.00);

        self::assertTrue($payment->getIsTransactionApproved());
        self::assertFalse($payment->getIsTransactionPending());
    }

    /**
     * Test with an exception when trying to capture an authorize transaction.
     *
     * @dataProvider authorizeTransactionsDataProvider
     *
     * @param string $captureStatus
     * @param array $transactionsData
     *
     * @return void
     * @throws CaptureErrorException
     * @throws LocalizedException
     * @throws NotEnoughFundException
     */
    public function testExecuteWithExceptionOnCapture(string $captureStatus, array $transactionsData): void
    {
        $invoiceMock = self::createMock(InvoiceInterface::class);
        $invoiceMock->expects(self::exactly(2))
            ->method('getEntityId')
            ->willReturn('123');

        $this->paymentMock->expects(self::exactly(3))
            ->method('getCreatedInvoice')
            ->willReturn($invoiceMock);

        $paymentOrderMock = self::createMock(Order::class);
        $paymentOrderMock->expects(self::atLeastOnce())
            ->method('getEntityId')
            ->willReturn('123');

        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getOrder')
            ->willReturn($paymentOrderMock);

        $this->allTransactionsToCaptureFinderMock->expects(self::once())
            ->method('execute')
            ->willReturn($this->createTransactions($transactionsData));

        $this->clientMock->expects(self::once())
            ->method('capture')
            ->willThrowException(new Exception())
        ;

        self::expectException(CaptureErrorException::class);

        $this->orderActionCaptureService->execute($this->paymentMock, 25.00);
    }

    /**
     * Test with authorize transactions in every status.
     *
     * @dataProvider authorizeTransactionsDataProvider
     *
     * @param string $captureStatus
     * @param array $transactionsData
     *
     * @return void
     * @throws CaptureErrorException
     * @throws LocalizedException
     * @throws NotEnoughFundException
     */
    public function testExecuteWithAuthorizeTransactions(string $captureStatus, array $transactionsData): void
    {
        $invoiceMock = self::createMock(InvoiceInterface::class);
        $invoiceMock->expects(self::exactly(2))
            ->method('getEntityId')
            ->willReturn('123');

        $this->paymentMock->expects(self::exactly(3))
            ->method('getCreatedInvoice')
            ->willReturn($invoiceMock);

        $paymentOrderMock = self::createMock(Order::class);
        $paymentOrderMock->expects(self::atLeastOnce())
            ->method('getEntityId')
            ->willReturn('123');

        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getOrder')
            ->willReturn($paymentOrderMock);

        $this->allTransactionsToCaptureFinderMock->expects(self::once())
            ->method('execute')
            ->willReturn($this->createTransactions($transactionsData));

        $this->clientMock->expects(self::once())
            ->method('capture')
            ->willReturn(['capture_transaction_data']);

        $captureTransactionMock = $this->createTransactions(
            [
                [
                    'sessionId' => 'fakeSessionId',
                    'status' => $captureStatus,
                    'amount' => 25.00,
                    'parentPaymentId' => 123,
                    'transactionType' => OrderTransactions::AUTHORIZE_ACTION,
                    'invoiceId' => null
                ]
            ]
        )[0]['transaction'];

        $this->orderTransactionsMock->expects(self::once())
            ->method('createTransactionFromResponse')
            ->willReturn($captureTransactionMock);

        if ($captureStatus !== OrderTransactions::ERROR_STATUS) {
            $orderPaymentMock = self::createMock(OrderPaymentInterface::class);

            $orderPaymentMock->expects(self::once())
                ->method('setAmountCaptured')
                ->with(50.00)
                ->willReturnSelf();

            $orderPaymentMock->expects(self::once())
                ->method('getAmountCaptured')
                ->willReturn(25.00);

            $this->orderPaymentRepositoryMock->expects(self::once())
                ->method('getById')
                ->with(123)
                ->willReturn($orderPaymentMock);
        }

        if ($captureStatus === OrderTransactions::SUCCESS_STATUS) {
            $this->testExecuteWithAuthorizeTransactionInSuccess();
        } elseif ($captureStatus === OrderTransactions::WAITING_STATUS) {
            $this->testExecuteWithAuthorizeTransactionInWaiting();
        } else {
            $this->testExecuteWithAuthorizeTransactionInError();
        }
    }

    /**
     * Relevant assertions in case of capture success from authorize transaction.
     *
     * @return void
     * @throws CaptureErrorException
     * @throws LocalizedException
     * @throws NotEnoughFundException
     */
    private function testExecuteWithAuthorizeTransactionInSuccess(): void
    {
        $this->floatComparatorMock->expects(self::once())
            ->method('equal')
            ->with(25.00, 25.00)
            ->willReturn(true)
        ;

        $this->paymentMock->expects(self::atMost(1))
            ->method('setTransactionId')
            ->with('fakeSessionId-123')
            ->willReturnSelf()
        ;

        $this->paymentMock->expects(self::atMost(1))
            ->method('setIsTransactionClosed')
            ->with(false)
            ->willReturnSelf()
        ;

        $this->paymentMock->expects(self::once())
            ->method('setIsTransactionApproved')
            ->with(true)
            ->willReturnSelf()
        ;

        $this->paymentMock->expects(self::once())
            ->method('getIsTransactionApproved')
            ->willReturn(true)
        ;

        $this->paymentMock->expects(self::once())
            ->method('setIsTransactionPending')
            ->with(false)
            ->willReturnSelf()
        ;

        $this->paymentMock->expects(self::once())
            ->method('getIsTransactionPending')
            ->willReturn(false)
        ;

        $payment = $this->orderActionCaptureService->execute($this->paymentMock, 25.00);

        self::assertTrue($payment->getIsTransactionApproved());
        self::assertFalse($payment->getIsTransactionPending());
    }

    /**
     * Relevant assertions in case of capture waiting from authorize transaction.
     *
     * @return void
     * @throws CaptureErrorException
     * @throws LocalizedException
     * @throws NotEnoughFundException
     */
    private function testExecuteWithAuthorizeTransactionInWaiting(): void
    {
        $this->paymentMock->expects(self::once())
            ->method('setIsTransactionPending')
            ->with(true)
            ->willReturnSelf()
        ;

        $this->paymentMock->expects(self::once())
            ->method('getIsTransactionPending')
            ->willReturn(true)
        ;

        $payment = $this->orderActionCaptureService->execute($this->paymentMock, 25.00);

        self::assertTrue($payment->getIsTransactionPending());
    }

    /**
     * Relevant assertions in case of capture error from authorize transaction.
     *
     * @return void
     * @throws CaptureErrorException
     * @throws LocalizedException
     * @throws NotEnoughFundException
     */
    private function testExecuteWithAuthorizeTransactionInError(): void
    {
        self::expectException(CaptureErrorException::class);
        $this->orderActionCaptureService->execute($this->paymentMock, 25.00);
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

            $transactionMock->method('getSessionId')
                ->willReturn($transactionData['sessionId'])
            ;

            $transactionMock->method('getStatus')
                ->willReturn($transactionData['status'])
            ;

            $transactionMock->method('getAmount')
                ->willReturn($transactionData['amount'])
            ;

            $transactionMock->method('getParentPaymentId')
                ->willReturn($transactionData['parentPaymentId'])
            ;

            $transactionMock->method('getTransactionType')
                ->willReturn($transactionData['transactionType'])
            ;

            $transactionMock->method('getInvoiceId')
                ->willReturn($transactionData['invoiceId'])
            ;

            $transactionMock->method('setInvoiceId')
                ->with(123)
                ->willReturnSelf()
            ;

            $transactions[] = [
                'transaction' => $transactionMock,
                'amountToCapture' => $transactionData['amount']
            ];
        }

        return $transactions;
    }

    /**
     * @return Generator
     */
    public function captureTransactionsDataProvider(): Generator
    {
        yield 'One capture transaction' => [
            [
                [
                    'sessionId' => 'fakeSessionId',
                    'status' => OrderTransactions::SUCCESS_STATUS,
                    'amount' => 25.00,
                    'parentPaymentId' => 123,
                    'transactionType' => OrderTransactions::CAPTURE_ACTION,
                    'invoiceId' => null
                ]
            ]
        ];
    }

    /**
     * @return Generator
     */
    public function authorizeTransactionsDataProvider(): Generator
    {
        yield 'One authorize transaction with success on capture' => [
            'captureStatus' => OrderTransactions::SUCCESS_STATUS,
            [
                [
                    'sessionId' => 'fakeSessionId',
                    'status' => OrderTransactions::SUCCESS_STATUS,
                    'amount' => 25.00,
                    'parentPaymentId' => 123,
                    'transactionType' => OrderTransactions::AUTHORIZE_ACTION,
                    'invoiceId' => null
                ]
            ]
        ];

        yield 'One authorize transaction with waiting on capture' => [
            'captureStatus' => OrderTransactions::WAITING_STATUS,
            [
                [
                    'sessionId' => 'fakeSessionId',
                    'status' => OrderTransactions::SUCCESS_STATUS,
                    'amount' => 25.00,
                    'parentPaymentId' => 123,
                    'transactionType' => OrderTransactions::AUTHORIZE_ACTION,
                    'invoiceId' => null
                ]
            ]
        ];

        yield 'One authorize transaction with error on capture' => [
            'captureStatus' => OrderTransactions::ERROR_STATUS,
            [
                [
                    'sessionId' => 'fakeSessionId',
                    'status' => OrderTransactions::SUCCESS_STATUS,
                    'amount' => 25.00,
                    'parentPaymentId' => 123,
                    'transactionType' => OrderTransactions::AUTHORIZE_ACTION,
                    'invoiceId' => null
                ]
            ]
        ];
    }
}
