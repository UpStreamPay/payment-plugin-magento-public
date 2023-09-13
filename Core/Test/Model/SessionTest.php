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

namespace UpStreamPay\Test\Core\Model;

use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Exception\CreateSessionException;
use UpStreamPay\Core\Model\PaymentMethod;
use UpStreamPay\Core\Model\Session;
use PHPUnit\Framework\TestCase;
use UpStreamPay\Core\Model\Session\Order\OrderService;
use Psr\Log\LoggerInterface;

/**
 * Class SessionTest
 *
 * @package UpStreamPay\Test\Core\Model
 */
class SessionTest extends TestCase
{
    private ClientInterface&MockObject $clientMock;
    private OrderService&MockObject $orderServiceMock;
    private CheckoutSession&MockObject $checkoutSessionMock;
    private LoggerInterface&MockObject $loggerMock;
    private PaymentMethod&MockObject $paymentMethodMock;
    private Session $session;

    protected function setUp(): void
    {
        $this->clientMock = self::createMock(ClientInterface::class);
        $this->orderServiceMock = self::createMock(OrderService::class);
        /** @var CheckoutSession&MockObject $session */
        $session = self::getMockBuilder(CheckoutSession::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getQuote']) // Keep method 'getQuote'
            ->addMethods(['setCartAmount']) // Add magic method 'setCartAmount'
            ->getMock();
        $this->checkoutSessionMock = $session;
        $this->loggerMock = self::createMock(LoggerInterface::class);
        $this->paymentMethodMock = self::createMock(PaymentMethod::class);
        $this->session = new Session($this->clientMock, $this->orderServiceMock, $this->checkoutSessionMock, $this->loggerMock, $this->paymentMethodMock);
    }

    public function testGetSessionException()
    {
        self::expectException(CreateSessionException::class);
        $this->loggerMock
            ->expects(self::once())
            ->method('critical');

        $this->session->getSession();
    }
    public function testGetSessionCreateException()
    {
        $quote = self::createMock(Quote::class);
        $quote
            ->expects(self::once())
            ->method('getId')
            ->willReturn(123);

        $this->checkoutSessionMock
            ->expects(self::once())
            ->method('getQuote')
            ->willReturn($quote);

        $this->orderServiceMock
            ->expects(self::once())
            ->method('execute')
            ->with($quote)
            ->willReturn([1, 2, 3]);

        $this->clientMock
            ->expects(self::once())
            ->method('createSession')
            ->willThrowException(new Exception());

        self::expectException(CreateSessionException::class);
        $this->loggerMock
            ->expects(self::exactly(2))
            ->method('critical');

        $this->session->getSession();
    }

    public function testGetSession()
    {
        $quote = self::createMock(Quote::class);
        $quote
            ->expects(self::once())
            ->method('getId')
            ->willReturn(123);

        $this->checkoutSessionMock
            ->expects(self::once())
            ->method('getQuote')
            ->willReturn($quote);

        $this->orderServiceMock
            ->expects(self::once())
            ->method('execute')
            ->with($quote)
            ->willReturn([1, 2, 3]);

        $sessionData = [
            'amount' => 456,
            'protocols' => [
                'partner' => [
                    'name1' => ['configurations' => ['labels' => [PaymentMethod::PRIMARY]]],
                    'name2' => ['configurations' => ['labels' => [PaymentMethod::SECONDARY]]],
                ]
            ]
        ];

        $this->clientMock
            ->expects(self::once())
            ->method('createSession')
            ->with([1, 2, 3])
            ->willReturn($sessionData);

        $this->paymentMethodMock
            ->expects(self::exactly(2))
            ->method('updateOrCreate')
            ->withConsecutive(
                ['partner / name1', PaymentMethod::PRIMARY],
                ['partner / name2', PaymentMethod::SECONDARY],
            );

        $this->checkoutSessionMock
            ->expects(self::once())
            ->method('setCartAmount')
            ->with(456);

        self::assertSame([$sessionData], $this->session->getSession());
    }
}
