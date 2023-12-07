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

namespace UpStreamPay\Core\Plugin;

use Magento\Checkout\Model\PaymentInformationManagement;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use UpStreamPay\Core\Exception\UnsynchronizedCartAmountsException;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Session\PurseSessionDataManager;

/**
 * Class PaymentInformationPluginTest
 *
 * @package UpStreamPay\Core\Plugin
 */
class PaymentInformationPluginTest extends TestCase
{
    private PaymentInformationPlugin $paymentInformationPlugin;
    private PurseSessionDataManager&MockObject $purseSessionDataManagerMock;
    private PaymentInformationManagement&MockObject $paymentInformationManagement;
    private PaymentInterface&MockObject $paymentMock;
    private AddressInterface&MockObject $addressMock;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $cartRepositoryMock = self::createMock(CartRepositoryInterface::class);
        $this->purseSessionDataManagerMock = self::createMock(PurseSessionDataManager::class);
        $this->paymentInformationManagement = self::createMock(PaymentInformationManagement::class);
        $this->paymentMock = self::createMock(PaymentInterface::class);
        $this->addressMock = self::createMock(AddressInterface::class);
        $quoteMock = self::createMock(Quote::class);

        $cartRepositoryMock->method('getActive')
            ->with(1)
            ->willReturn($quoteMock)
        ;

        $this->paymentInformationPlugin = new PaymentInformationPlugin(
            $cartRepositoryMock,
            $this->purseSessionDataManagerMock
        );
    }

    /**
     * @return void
     * @throws NoSuchEntityException
     * @throws UnsynchronizedCartAmountsException
     */
    public function testBeforeSavePaymentInformationAndPlaceOrderWithUpStreamPayPaymentMethod(): void
    {
        $this->paymentMock->expects(self::once())
            ->method('getMethod')
            ->willReturn(Config::METHOD_CODE_UPSTREAM_PAY)
        ;

        $this->purseSessionDataManagerMock->expects(self::once())
            ->method('validatePurseSessionAmountBeforePlaceOrder')
        ;
        $this->purseSessionDataManagerMock->expects(self::never())
            ->method('cleanPurseSessionDataFromQuotePayment')
        ;

        $this->paymentInformationPlugin->beforeSavePaymentInformationAndPlaceOrder(
            $this->paymentInformationManagement,
            1,
            $this->paymentMock,
            $this->addressMock
        );
    }

    /**
     * @return void
     * @throws NoSuchEntityException
     * @throws UnsynchronizedCartAmountsException
     */
    public function testBeforeSavePaymentInformationAndPlaceOrderWithoutUpStreamPayPaymentMethod(): void
    {
        $this->paymentMock->expects(self::once())
            ->method('getMethod')
            ->willReturn('paypal')
        ;

        $this->purseSessionDataManagerMock->expects(self::once())
            ->method('cleanPurseSessionDataFromQuotePayment')
        ;
        $this->purseSessionDataManagerMock->expects(self::never())
            ->method('validatePurseSessionAmountBeforePlaceOrder')
        ;

        $this->paymentInformationPlugin->beforeSavePaymentInformationAndPlaceOrder(
            $this->paymentInformationManagement,
            1,
            $this->paymentMock,
            $this->addressMock
        );
    }
}
