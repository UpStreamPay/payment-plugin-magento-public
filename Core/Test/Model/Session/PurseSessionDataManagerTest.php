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

namespace UpStreamPay\Core\Model\Session;

use Magento\Framework\Math\FloatComparator;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Payment;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Exception\UnsynchronizedCartAmountsException;

/**
 * Class PurseSessionDataManagerTest
 *
 * @package UpStreamPay\Core\Model\Session
 */
class PurseSessionDataManagerTest extends TestCase
{
    private PurseSessionDataManager $purseSessionDataManager;
    private LoggerInterface&MockObject $loggerMock;
    private FloatComparator&MockObject $floatComparatorMock;
    private Quote&MockObject $quoteMock;
    private Payment&MockObject $quotePaymentMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->loggerMock = self::createMock(LoggerInterface::class);
        $this->floatComparatorMock = self::createMock(FloatComparator::class);
        $cartRepositoryInterfaceMock = self::createMock(CartRepositoryInterface::class);
        $cartRepositoryInterfaceMock
            ->expects(self::atMost(2))
            ->method('save')
            ->willReturn(self::createMock(CartInterface::class))
        ;

        $this->quotePaymentMock = self::createMock(Payment::class);

        //Because of magic methods.
        $methods = array_merge(
            get_class_methods(Quote::class),
            ['getBaseGrandTotal']
        );
        $this->quoteMock = self::createPartialMock(Quote::class, $methods);
        $this->quoteMock
            ->expects(self::atMost(3))
            ->method('getBaseGrandTotal')
            ->willReturn(34.56)
        ;
        $this->quoteMock
            ->expects(self::atMost(5))
            ->method('getPayment')
            ->willReturn($this->quotePaymentMock)
        ;

        $this->purseSessionDataManager = new PurseSessionDataManager(
            $cartRepositoryInterfaceMock,
            $this->floatComparatorMock,
            $this->loggerMock
        );
    }

    /**
     * @return void
     * @throws UnsynchronizedCartAmountsException
     */
    public function testValidatePurseSessionAmountBeforePlaceOrder(): void
    {
        $this->quotePaymentMock
            ->expects(self::once())
            ->method('getData')
            ->with(PurseSessionDataManager::QUOTE_PAYMENT_PURSE_SESSION_AMOUNT_KEY)
            ->willReturn($this->quoteMock->getBaseGrandTotal())
        ;

        $this->floatComparatorMock
            ->expects(self::once())
            ->method('equal')
            ->willReturnOnConsecutiveCalls(true)
        ;

        $this->purseSessionDataManager->validatePurseSessionAmountBeforePlaceOrder($this->quoteMock, 234);
    }

    /**
     * @return void
     * @throws UnsynchronizedCartAmountsException
     */
    public function testValidatePurseSessionAmountBeforePlaceOrderWithException(): void
    {
        $this->quotePaymentMock
            ->expects(self::exactly(2))
            ->method('getData')
            ->with(PurseSessionDataManager::QUOTE_PAYMENT_PURSE_SESSION_AMOUNT_KEY)
            ->willReturn(34.50)
        ;

        $this->floatComparatorMock
            ->expects(self::once())
            ->method('equal')
            ->willReturnOnConsecutiveCalls(false)
        ;

        $this->loggerMock
            ->expects(self::once())
            ->method('critical')
        ;

        self::expectException(UnsynchronizedCartAmountsException::class);
        $this->purseSessionDataManager->validatePurseSessionAmountBeforePlaceOrder($this->quoteMock, 234);
    }

    /**
     * @return void
     */
    public function testSetPurseSessionDataInQuote(): void
    {
        $this->quotePaymentMock
            ->expects(self::exactly(2))
            ->method('setData')
            ->withConsecutive(
                [PurseSessionDataManager::QUOTE_PAYMENT_PURSE_SESSION_AMOUNT_KEY, 34.50],
                [PurseSessionDataManager::PAYMENT_PURSE_SESSION_ID, 'rez43']
            )
            ->willReturnSelf()
        ;

        $this->purseSessionDataManager->setPurseSessionDataInQuote(
            [
                'amount' => 34.50,
                'id' => 'rez43'
            ],
            $this->quoteMock
        );
    }

    /**
     * @return void
     */
    public function testCleanPurseSessionDataFromQuotePayment(): void
    {
        $this->quotePaymentMock
            ->expects(self::once())
            ->method('getData')
            ->with(PurseSessionDataManager::PAYMENT_PURSE_SESSION_ID)
            ->willReturn('343dfdf')
        ;

        $this->quotePaymentMock
            ->expects(self::exactly(2))
            ->method('setData')
            ->withConsecutive(
                [PurseSessionDataManager::QUOTE_PAYMENT_PURSE_SESSION_AMOUNT_KEY, 0.00],
                [PurseSessionDataManager::PAYMENT_PURSE_SESSION_ID, null]
            )
            ->willReturnSelf()
        ;

        $this->purseSessionDataManager->cleanPurseSessionDataFromQuotePayment($this->quoteMock);
    }

    /**
     * @return void
     */
    public function testCleanPurseSessionDataFromQuotePaymentWithNoPurseData(): void
    {
        $this->quotePaymentMock
            ->expects(self::once())
            ->method('getData')
            ->with(PurseSessionDataManager::PAYMENT_PURSE_SESSION_ID)
            ->willReturn(null)
        ;

        $this->quotePaymentMock
            ->expects(self::never())
            ->method('setData')
        ;

        $this->purseSessionDataManager->cleanPurseSessionDataFromQuotePayment($this->quoteMock);
    }
}
