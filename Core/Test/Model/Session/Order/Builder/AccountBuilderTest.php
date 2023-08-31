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

namespace UpStreamPay\Core\Test\Model\Session\Order\Builder;

use DateTime;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\Log;
use Magento\Customer\Model\Logger;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Session\Order\Builder\AccountBuilder;
use PHPUnit\Framework\TestCase;
use function PHPUnit\Framework\exactly;

/**
 * Class AccountBuilderTest
 *
 * @package UpStreamPay\Core\Test\Model\Session\Order\Builder
 */
class AccountBuilderTest extends TestCase
{
    private TimezoneInterface&MockObject $timezoneMock;
    private Logger&MockObject $loggerMock;
    private Config&MockObject $configMock;
    private AccountBuilder $accountBuilder;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->timezoneMock = self::createMock(TimezoneInterface::class);
        $this->loggerMock = self::createMock(Logger::class);
        $this->configMock = self::createMock(Config::class);

        $this->accountBuilder = new AccountBuilder(
            $this->timezoneMock,
            $this->loggerMock,
            $this->configMock,
        );
    }

    /**
     * @return void
     */
    public function testExecuteGuest(): void
    {
        $quoteMock = self::createMock(Quote::class);
        $quoteMock->expects(self::once())
            ->method('getCustomerIsGuest')
            ->willReturn(true)
        ;

        $this->timezoneMock->expects(self::once())
            ->method('date')
            ->willReturn(new DateTime('1900-01-01'))
        ;

        $accountData = $this->accountBuilder->execute($quoteMock);
        $expectedAccountData = [
            'creation_date_time' => '1900-01-01T00:00:00-08:00',
            'update_date_time' => '1900-01-01T00:00:00-08:00',
            'authentication_method' => 'GUEST',
            'age_indicator' => 'GUEST',
            'change_indicator' => 'NEW',
        ];

        self::assertSame($expectedAccountData, $accountData);
    }

    /**
     * @return void
     */
    public function testExecuteCustomerWithNo3dsAttribute(): void
    {
        $customerMock = self::createMock(CustomerInterface::class);
        $customerMock->expects(self::once())
            ->method('getCreatedAt')
            ->willReturn('2023-01-01 00:00:00')
        ;
        $customerMock->expects(self::once())
            ->method('getUpdatedAt')
            ->willReturn('2023-01-01 00:00:00')
        ;
        $customerMock->expects(self::once())
            ->method('getId')
            ->willReturn('123')
        ;

        $quoteMock = self::createMock(Quote::class);
        $quoteMock->expects(self::once())
            ->method('getCustomerIsGuest')
            ->willReturn(false)
        ;
        $quoteMock->expects(self::once())
            ->method('getCustomer')
            ->willReturn($customerMock)
        ;

        $this->timezoneMock->expects(self::any())
            ->method('date')
            ->willReturn(new DateTime('2023-01-01 00:00:00'))
        ;

        $customerLogMock = self::createMock(Log::class);
        $customerLogMock->expects(self::once())
            ->method('getLastLoginAt')
            ->willReturn('2023-01-01 00:00:00')
        ;

        $this->loggerMock->expects(self::once())
            ->method('get')
            ->willReturn($customerLogMock)
        ;

        $this->configMock->expects(self::once())
            ->method('get3dsExemptionAttributeCode')
            ->willReturn('3ds_exemption_attribute_code')
        ;

        $this->configMock->expects(self::once())
            ->method('get3dsChallengeIndicatorAttributeCode')
            ->willReturn('3ds_exemption_attribute_code')
        ;

        $quoteMock->expects(exactly(2))
            ->method('getData')
            ->willReturn(null)
        ;

        $expectedAccountData = [
            'creation_date_time' => '2023-01-01T00:00:00-08:00',
            'update_date_time' => '2023-01-01T00:00:00-08:00',
            'authentication_method' => 'MERCHANT_CREDENTIALS',
            'authentication_date_time' => '2023-01-01T00:00:00-08:00',
            'age_indicator' => 'NEW',
            'change_indicator' => 'NEW',
        ];

        self::assertSame($expectedAccountData, $this->accountBuilder->execute($quoteMock));
    }

    /**
     * @return void
     */
    public function testExecuteCustomerWith3dsAttribute(): void
    {
        $customerMock = self::createMock(CustomerInterface::class);
        $customerMock->expects(self::once())
            ->method('getCreatedAt')
            ->willReturn('2023-01-01 00:00:00')
        ;
        $customerMock->expects(self::once())
            ->method('getUpdatedAt')
            ->willReturn('2023-01-01 00:00:00')
        ;
        $customerMock->expects(self::once())
            ->method('getId')
            ->willReturn('123')
        ;

        $quoteMock = self::createMock(Quote::class);
        $quoteMock->expects(self::once())
            ->method('getCustomerIsGuest')
            ->willReturn(false)
        ;
        $quoteMock->expects(self::once())
            ->method('getCustomer')
            ->willReturn($customerMock)
        ;

        $this->timezoneMock->expects(self::any())
            ->method('date')
            ->willReturn(new DateTime('2023-01-01 00:00:00'))
        ;

        $customerLogMock = self::createMock(Log::class);
        $customerLogMock->expects(self::once())
            ->method('getLastLoginAt')
            ->willReturn('2023-01-01 00:00:00')
        ;

        $this->loggerMock->expects(self::once())
            ->method('get')
            ->willReturn($customerLogMock)
        ;

        $this->configMock->expects(self::once())
            ->method('get3dsExemptionAttributeCode')
            ->willReturn('3ds_exemption_attribute_code')
        ;

        $this->configMock->expects(self::once())
            ->method('get3dsChallengeIndicatorAttributeCode')
            ->willReturn('3ds_challenge_indicator_attribute_code')
        ;

        $quoteMock->expects(exactly(4))
            ->method('getData')
            ->willReturnOnConsecutiveCalls(
                '3ds_exemption_attribute_value',
                '3ds_exemption_attribute_value',
                '3ds_challenge_indicator_attribute_value',
                '3ds_challenge_indicator_attribute_value'
            )
        ;

        $expectedAccountData = [
            'creation_date_time' => '2023-01-01T00:00:00-08:00',
            'update_date_time' => '2023-01-01T00:00:00-08:00',
            'authentication_method' => 'MERCHANT_CREDENTIALS',
            'authentication_date_time' => '2023-01-01T00:00:00-08:00',
            'age_indicator' => 'NEW',
            'change_indicator' => 'NEW',
            'three_ds_exemption' => '3ds_exemption_attribute_value',
            'challenge_indicator' => '3ds_challenge_indicator_attribute_value',
        ];

        self::assertSame($expectedAccountData, $this->accountBuilder->execute($quoteMock));
    }
}
