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
namespace UpStreamPay\Client\Test\Model\Token;

use DateTime;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use PHPUnit\Framework\TestCase;
use UpStreamPay\Client\Model\Token\Token;
use UpStreamPay\Client\Model\Token\TokenFactory;
use UpStreamPay\Client\Model\Token\TokenService;

/**
 * Class TokenServiceTest
 *
 * @package UpStreamPay\Client\Test\Model\Token
 */
class TokenServiceTest extends TestCase
{
    private TokenFactory $tokenFactoryMock;
    private TimezoneInterface $timezoneMock;
    private CacheInterface $cacheMock;
    private SerializerInterface $serializerMock;
    private TokenService $tokenService;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->tokenFactoryMock = self::createMock(TokenFactory::class);
        $this->timezoneMock = self::createMock(TimezoneInterface::class);
        $this->cacheMock = self::createMock(CacheInterface::class);
        $this->serializerMock = self::createMock(SerializerInterface::class);

        $this->tokenFactoryMock
            ->expects(self::any())
            ->method('create')
            ->willReturn(new Token())
        ;

        $this->tokenService = new TokenService(
            $this->tokenFactoryMock,
            $this->timezoneMock,
            $this->cacheMock,
            $this->serializerMock
        );
    }

    /**
     * @return void
     */
    public function testSetToken(): void
    {
        $tokenData = [
            'access_token' => '456465fdgsdfgdg',
            'expires_in' => 1440
        ];

        $this->timezoneMock
            ->expects(self::once())
            ->method('date')
            ->willReturn(new DateTime('1900-12-12 00:00:00'))
        ;

        $token = $this->tokenService->setToken($tokenData);

        self::assertSame('456465fdgsdfgdg', $token->getValue());
        self::assertSame(1440, $token->getLifetime());
        self::assertSame('1900-12-12 00:00:00', $token->getCreatedAt());
        self::assertSame('1900-12-12 00:19:00', $token->getExpirationDate());
        //Because we add the lifetime to the creation date, not substract it from the creation date.
        self::assertNotSame('1900-12-11 00:41:00', $token->getExpirationDate());
    }

    /**
     * @return void
     */
    public function testGetTokenWithNoCache(): void
    {
        $this->cacheMock
            ->expects(self::once())
            ->method('load')
            ->willReturn(false)
        ;

        $token = $this->tokenService->getToken();

        self::assertNull($token->getValue());
        self::assertSame(0, $token->getLifetime());
        self::assertNull($token->getCreatedAt());
        self::assertNull($token->getExpirationDate());
    }

    /**
     * @return void
     */
    public function testGetTokenWithWrongCacheData(): void
    {
        $this->cacheMock
            ->expects(self::once())
            ->method('load')
            ->willReturn('{}')
        ;

        $this->serializerMock
            ->expects(self::once())
            ->method('unserialize')
            ->willReturn(null)
        ;

        $token = $this->tokenService->getToken();

        self::assertNull($token->getValue());
        self::assertSame(0, $token->getLifetime());
        self::assertNull($token->getCreatedAt());
        self::assertNull($token->getExpirationDate());
    }

    /**
     * @return void
     */
    public function testGetToken(): void
    {
        $value = 'YpoBENvDM';
        $lifetime = 14400;
        $createdAt = '2023-08-22 10:18:55';
        $expirationDate = '2023-08-22 14:13:55';

        $this->cacheMock
            ->expects(self::once())
            ->method('load')
            ->willReturn(
                sprintf(
                    '{"value":"%s","lifetime":%s,"created_at":"%s","expiration_date":"%s"}',
                    $value,
                    $lifetime,
                    $createdAt,
                    $expirationDate
                )
            )
        ;

        $this->serializerMock
            ->expects(self::once())
            ->method('unserialize')
            ->willReturn([
                'value' => $value,
                'lifetime' => $lifetime,
                'created_at' => $createdAt,
                'expiration_date' => $expirationDate
            ])
        ;

        $token = $this->tokenService->getToken();

        self::assertSame($value, $token->getValue());
        self::assertSame($lifetime, $token->getLifetime());
        self::assertSame($createdAt, $token->getCreatedAt());
        self::assertSame($expirationDate, $token->getExpirationDate());
    }
}
