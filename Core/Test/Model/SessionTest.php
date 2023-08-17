<?php

namespace UpStreamPay\Test\Core\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\MockObject\MockObject;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Model\PaymentMethod;
use UpStreamPay\Core\Model\Session;
use PHPUnit\Framework\TestCase;
use UpStreamPay\Core\Model\Session\Order\OrderService;
use Psr\Log\LoggerInterface;

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
        self::expectException(\Exception::class);
        $this->loggerMock
            ->expects(self::once())
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
