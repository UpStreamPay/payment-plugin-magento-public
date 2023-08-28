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
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Api\Data\OrderPaymentInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Model\Actions\RefundService;
use PHPUnit\Framework\TestCase;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\PaymentFinder\AllTransactionsToRefundFinder;

/**
 * Class RefundServiceTest
 *
 * @package UpStreamPay\Core\Test\Model\Actions
 */
class RefundServiceTest extends TestCase
{
    private AllTransactionsToRefundFinder&MockObject $allTransactionsToRefundFinderMock;
    private ClientInterface&MockObject $clientMock;
    private OrderTransactions&MockObject $orderTransactionsMock;
    private OrderPaymentRepositoryInterface&MockObject $orderPaymentRepositoryMock;
    private Payment&MockObject $paymentMock;
    private RefundService $refundService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->allTransactionsToRefundFinderMock = self::createMock(AllTransactionsToRefundFinder::class);
        $this->clientMock = self::createMock(ClientInterface::class);
        $this->orderTransactionsMock = self::createMock(OrderTransactions::class);
        $this->orderPaymentRepositoryMock = self::createMock(OrderPaymentRepositoryInterface::class);
        $this->paymentMock = self::createMock(Payment::class);

        $this->refundService = new RefundService(
            $this->allTransactionsToRefundFinderMock,
            $this->clientMock,
            $this->orderTransactionsMock,
            $this->orderPaymentRepositoryMock,
            self::createMock(LoggerInterface::class),
            self::createMock(ManagerInterface::class)
        );
    }

    /**
     * @dataProvider transactionsDataProvider
     *
     * @param array $transactionsData
     *
     * @return void
     * @throws LocalizedException
     */
    public function testExecuteWithRefundException(array $transactionsData): void
    {
        $orderMock = self::createMock(Order::class);
        $orderMock->expects(self::atLeastOnce())
            ->method('getEntityId')
            ->willReturn('123')
        ;
        $invoiceMock = self::createMock(InvoiceInterface::class);
        $invoiceMock->expects(self::atLeastOnce())
            ->method('getEntityId')
            ->willReturn('123')
        ;
        $creditmemoMock = self::createMock(Creditmemo::class);
        $creditmemoMock->expects(self::atLeastOnce())
            ->method('getInvoice')
            ->willReturn($invoiceMock)
        ;

        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getOrder')
            ->willReturn($orderMock)
        ;

        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getCreditmemo')
            ->willReturn($creditmemoMock)
        ;

        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getCreditmemo')
            ->willReturn(self::createMock(CreditmemoInterface::class))
        ;

        $this->allTransactionsToRefundFinderMock->expects(self::atLeastOnce())
            ->method('execute')
            ->willReturn($this->createTransactions($transactionsData))
        ;

        $orderPaymentMock = self::createMock(OrderPaymentInterface::class);
        $orderPaymentMock->expects(self::atLeastOnce())
            ->method('getAmountRefunded')
            ->willReturn(10.00)
        ;

        $orderPaymentMock->expects(self::atLeastOnce())
            ->method('getAmount')
            ->willReturn(20.00)
        ;

        $this->orderPaymentRepositoryMock->expects(self::atLeastOnce())
            ->method('getById')
            ->with(123)
            ->willReturn($orderPaymentMock)
        ;

        $this->clientMock->expects(self::atLeastOnce())
            ->method('refund')
            ->willThrowException(new Exception())
        ;

        //This is the only relevant thing to check. Here we triggered an over refund, so the code should adapt & not
        //set the refunded amount to 15 but 20.
        $orderPaymentMock->expects(self::atLeastOnce())
            ->method('setAmountRefunded')
            ->with(20.00)
            ->willReturnSelf()
        ;

        $this->refundService->execute($this->paymentMock, 30.00);
    }

    /**
     * @dataProvider transactionsDataProvider
     *
     * @param array $transactionsData
     *
     * @return void
     * @throws LocalizedException
     */
    public function testExecute(array $transactionsData): void
    {
        $orderMock = self::createMock(Order::class);
        $orderMock->expects(self::atLeastOnce())
            ->method('getEntityId')
            ->willReturn('123')
        ;
        $invoiceMock = self::createMock(InvoiceInterface::class);
        $invoiceMock->expects(self::atLeastOnce())
            ->method('getEntityId')
            ->willReturn('123')
        ;
        $creditmemoMock = self::createMock(Creditmemo::class);
        $creditmemoMock->expects(self::atLeastOnce())
            ->method('getInvoice')
            ->willReturn($invoiceMock)
        ;

        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getOrder')
            ->willReturn($orderMock)
        ;

        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getCreditmemo')
            ->willReturn($creditmemoMock)
        ;

        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getCreditmemo')
            ->willReturn(self::createMock(CreditmemoInterface::class))
        ;

        $this->allTransactionsToRefundFinderMock->expects(self::atLeastOnce())
            ->method('execute')
            ->willReturn($this->createTransactions($transactionsData))
        ;

        $orderPaymentMock = self::createMock(OrderPaymentInterface::class);
        $orderPaymentMock->expects(self::atLeastOnce())
            ->method('getAmountRefunded')
            ->willReturn(00.00)
        ;

        $orderPaymentMock->expects(self::atLeastOnce())
            ->method('getAmount')
            ->willReturn(20.00)
        ;

        $this->orderPaymentRepositoryMock->expects(self::atLeastOnce())
            ->method('getById')
            ->with(123)
            ->willReturn($orderPaymentMock)
        ;

        $refundTransactionMock = self::createMock(OrderTransactionsInterface::class);
        $refundTransactionMock->expects(self::atLeastOnce())
            ->method('getAmount')
            ->willReturn(10.00)
        ;

        $refundTransactionMock->expects(self::atLeastOnce())
            ->method('getStatus')
            ->willReturn(OrderTransactions::ERROR_STATUS)
        ;

        $this->clientMock->expects(self::atLeastOnce())
            ->method('refund')
            ->willReturn(['transaction_data'])
        ;

        $this->orderTransactionsMock->expects(self::atLeastOnce())
            ->method('createTransactionFromResponse')
            ->willReturn($refundTransactionMock)
        ;

        $orderPaymentMock->expects(self::atLeastOnce())
            ->method('setAmountRefunded')
            ->with(10.00)
            ->willReturnSelf()
        ;

        $this->refundService->execute($this->paymentMock, 30.00);
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

            $transactionMock->expects(self::atLeastOnce())
                ->method('getParentPaymentId')
                ->willReturn($transactionData['parentPaymentId'])
            ;

            $transactionMock->expects(self::atLeastOnce())
                ->method('getTransactionId')
                ->willReturn($transactionData['transactionId'])
            ;

            $transactions[] = [
                'amountToRefund' => $transactionData['amount'],
                'transaction' => $transactionMock,
            ];
        }

        return $transactions;
    }

    /**
     * @return Generator
     */
    public function transactionsDataProvider(): Generator
    {
        yield 'One capture transaction' => [
            [
                [
                    'parentPaymentId' => 123,
                    'transactionId' => '123',
                    'amount' => 30.00,
                ]
            ]
        ];

        yield 'Two capture transaction' => [
            [
                [
                    'parentPaymentId' => 123,
                    'transactionId' => '123',
                    'amount' => 15.00,
                ]
            ],
            [
                [
                    'parentPaymentId' => 123,
                    'transactionId' => '123',
                    'amount' => 15.00,
                ]
            ]
        ];
    }
}
