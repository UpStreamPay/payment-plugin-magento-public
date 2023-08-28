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

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\Api\SearchCriteria;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Model\MethodInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Api\Data\OrderPaymentInterface;
use UpStreamPay\Core\Api\Data\OrderPaymentSearchResultsInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Model\Actions\VoidService;
use PHPUnit\Framework\TestCase;
use UpstreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Config\Source\Debug;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\PaymentFinder\AllTransactionsFinder;

/**
 * Class VoidServiceTest
 *
 * @package UpStreamPay\Core\Test\Model\Actions
 */
class VoidServiceTest extends TestCase
{
    private SearchCriteriaBuilder&MockObject $searchCriteriaBuilderMock;
    private ClientInterface&MockObject $clientMock;
    private OrderTransactions&MockObject $orderTransactionsMock;
    private OrderPaymentRepositoryInterface&MockObject $orderPaymentRepositoryMock;
    private Config&MockObject $configMock;
    private AllTransactionsFinder&MockObject $allTransactionsFinderMock;
    private Payment&MockObject $paymentMock;
    private VoidService $voidService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->searchCriteriaBuilderMock = self::createMock(SearchCriteriaBuilder::class);
        $this->clientMock = self::createMock(ClientInterface::class);
        $this->orderTransactionsMock = self::createMock(OrderTransactions::class);
        $this->orderPaymentRepositoryMock = self::createMock(OrderPaymentRepositoryInterface::class);
        $this->configMock = self::createMock(Config::class);
        $this->allTransactionsFinderMock = self::createMock(AllTransactionsFinder::class);
        $this->paymentMock = self::createMock(Payment::class);

        $this->configMock->method('getDebugMode')
            ->willReturn(Debug::DEBUG_VALUE)
        ;

        $this->voidService = new VoidService(
            $this->searchCriteriaBuilderMock,
            $this->clientMock,
            $this->orderTransactionsMock,
            $this->orderPaymentRepositoryMock,
            $this->configMock,
            $this->allTransactionsFinderMock,
            self::createMock(LoggerInterface::class),
            self::createMock(ManagerInterface::class)
        );
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     * @throws LocalizedException
     */
    public function testExecuteWithNoValidPaymentAction(): void
    {
        $this->configMock->expects(self::atLeastOnce())
            ->method('getPaymentAction')
            ->willReturn('invalid')
        ;

        $this->voidService->execute($this->paymentMock, 50.00);
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     * @throws LocalizedException
     */
    public function testExecuteVoidAuthorizeNoTransactions(): void
    {
        $orderMock = self::createMock(Order::class);
        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getOrder')
            ->willReturn($orderMock)
        ;
        $this->configMock->expects(self::atLeastOnce())
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_AUTHORIZE)
        ;

        $this->allTransactionsFinderMock->expects(self::atLeastOnce())
            ->method('execute')
            ->willReturn([])
        ;

        $this->voidService->execute($this->paymentMock, 50.00);
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     * @throws LocalizedException
     */
    public function testExecuteVoidCaptureNoTransactions(): void
    {
        $orderMock = self::createMock(Order::class);
        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getOrder')
            ->willReturn($orderMock)
        ;
        $this->configMock->expects(self::atLeastOnce())
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_AUTHORIZE_CAPTURE)
        ;

        $this->allTransactionsFinderMock->expects(self::atLeastOnce())
            ->method('execute')
            ->willReturn([])
        ;

        $this->voidService->execute($this->paymentMock, 50.00);
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     * @throws LocalizedException
     */
    public function testExecuteVoidCaptureAndAuthorizeNoTransactions(): void
    {
        $orderMock = self::createMock(Order::class);
        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getOrder')
            ->willReturn($orderMock)
        ;
        $this->configMock->expects(self::atLeastOnce())
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_ORDER)
        ;

        $this->allTransactionsFinderMock->expects(self::atLeastOnce())
            ->method('execute')
            ->willReturn([])
        ;

        $this->voidService->execute($this->paymentMock, 50.00);
    }

    /**
     * There is not much to test here, no real custom logic implemented.
     *
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     * @throws LocalizedException
     */
    public function testExecuteVoidAuthorize(): void
    {
        $orderMock = self::createMock(Order::class);
        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getOrder')
            ->willReturn($orderMock)
        ;
        $this->configMock->expects(self::atLeastOnce())
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_AUTHORIZE)
        ;

        $transactionMock = self::createMock(OrderTransactionsInterface::class);
        $transactionMock->expects(self::atLeastOnce())
            ->method('getAmount')
            ->willReturn(50.00)
        ;
        $transactionMock->expects(self::atLeastOnce())
            ->method('getTransactionId')
            ->willReturn('123')
        ;

        $this->allTransactionsFinderMock->expects(self::atLeastOnce())
            ->method('execute')
            ->willReturn([$transactionMock])
        ;

        $this->clientMock->expects(self::once())
            ->method('void')
            ->willReturn(['void_transaction_data'])
        ;

        $this->voidService->execute($this->paymentMock);
    }

    /**
     * There is not much to test here, no real custom logic implemented.
     *
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     * @throws LocalizedException
     */
    public function testExecuteVoidCapture(): void
    {
        $orderMock = self::createMock(Order::class);
        $this->paymentMock->expects(self::atLeastOnce())
            ->method('getOrder')
            ->willReturn($orderMock)
        ;
        $this->configMock->expects(self::atLeastOnce())
            ->method('getPaymentAction')
            ->willReturn(MethodInterface::ACTION_AUTHORIZE_CAPTURE)
        ;

        $transactionMock = self::createMock(OrderTransactionsInterface::class);
        $transactionMock->expects(self::atLeastOnce())
            ->method('getAmount')
            ->willReturn(50.00)
        ;
        $transactionMock->expects(self::atLeastOnce())
            ->method('getTransactionId')
            ->willReturn('123')
        ;

        $this->allTransactionsFinderMock->expects(self::atLeastOnce())
            ->method('execute')
            ->willReturn([$transactionMock])
        ;

        $this->clientMock->expects(self::once())
            ->method('refund')
            ->willReturn(['refund_transaction_data'])
        ;

        $refundTransactionMock = self::createMock(OrderTransactionsInterface::class);
        $refundTransactionMock->expects(self::atLeastOnce())
            ->method('getStatus')
            ->willReturn(OrderTransactions::ERROR_STATUS)
        ;
        $refundTransactionMock->expects(self::atLeastOnce())
            ->method('getParentPaymentId')
            ->willReturn(123)
        ;
        $refundTransactionMock->expects(self::atLeastOnce())
            ->method('getAmount')
            ->willReturn(50.00)
        ;

        $this->orderTransactionsMock->expects(self::once())
            ->method('createTransactionFromResponse')
            ->willReturn($refundTransactionMock)
        ;

        $searchCriteriaMock = self::createMock(SearchCriteria::class);

        $this->searchCriteriaBuilderMock->expects(self::once())
            ->method('create')
            ->willReturn($searchCriteriaMock)
        ;

        $paymentMock = self::createMock(OrderPaymentInterface::class);
        $paymentMock->expects(self::atLeastOnce())
            ->method('getEntityId')
            ->willReturn(123)
        ;
        $paymentMock->expects(self::atLeastOnce())
            ->method('getAmountRefunded')
            ->willReturn(00.00)
        ;
        //The only real important data that is calculated & saved.
        $paymentMock->expects(self::atLeastOnce())
            ->method('setAmountRefunded')
            ->with(50.00)
            ->willReturnSelf()
        ;

        $orderPaymentSearchResultsMock = self::createMock(OrderPaymentSearchResultsInterface::class);

        $orderPaymentSearchResultsMock->expects(self::once())
            ->method('getItems')
            ->willReturn([$paymentMock])
        ;

        $this->orderPaymentRepositoryMock->expects(self::once())
            ->method('getList')
            ->with($searchCriteriaMock)
            ->willReturn($orderPaymentSearchResultsMock)
        ;

        $this->voidService->execute($this->paymentMock);
    }
}
