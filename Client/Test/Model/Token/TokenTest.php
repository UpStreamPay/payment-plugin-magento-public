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

namespace UpStreamPay\Client\Test\Model\Token;

use UpStreamPay\Client\Model\Token\Token;
use PHPUnit\Framework\TestCase;

/**
 * Class TokenTest
 *
 * @package UpStreamPay\Client\Test\Model\Token
 */
class TokenTest extends TestCase
{
    /**
     * Data provider to test the token validity.
     *
     * @return iterable
     */
    public function tokenProvider(): iterable
    {
        yield 'Valid token' => [
            'value' => '4564g65fsd4gsdfg',
            'lifetime' => 1440,
            'createdAt' => '1900-12-12 00:00:00',
            'expirationDate' => '1900-12-12 00:24:00',
            'hasExpired' => false,
        ];

        yield 'Expired token' => [
            'value' => '4564g65fsd4gsdfg',
            'lifetime' => 1440,
            'createdAt' => '1900-12-12 00:00:00',
            'expirationDate' => '1900-12-11 00:24:00',
            'hasExpired' => true,
        ];

        yield 'No creation date token' => [
            'value' => '4564g65fsd4gsdfg',
            'lifetime' => 1440,
            'createdAt' => null,
            'expirationDate' => '1900-12-12 00:24:00',
            'hasExpired' => true,
        ];

        yield 'No expiration date token' => [
            'value' => '4564g65fsd4gsdfg',
            'lifetime' => 1440,
            'createdAt' => '1900-12-12 00:00:00',
            'expirationDate' => null,
            'hasExpired' => true,
        ];
    }

    /**
     * @return void
     */
    public function testTokenCreatedAt(): void
    {
        self::assertNull((new Token())->getCreatedAt());
        self::assertSame('1900-12-12 00:00:00', (new Token())->setCreatedAt('1900-12-12 00:00:00')->getCreatedAt());
    }

    /**
     * @return void
     */
    public function testTokenValue(): void
    {
        self::assertNull((new Token())->getValue());
        self::assertSame('4g5fds4gdsgsdg', (new Token())->setValue('4g5fds4gdsgsdg')->getValue());
    }

    /**
     * @return void
     */
    public function testTokenExpirationDate(): void
    {
        self::assertNull((new Token())->getExpirationDate());
        self::assertSame('1900-12-12 00:00:00', (
            new Token())->setExpirationDate('1900-12-12 00:00:00')->getExpirationDate()
        );
    }

    /**
     * @return void
     */
    public function testTokenLifetime(): void
    {
        self::assertSame(0, (new Token())->getLifetime());
        self::assertSame(1440, (new Token())->setLifetime(1440)->getLifetime());
    }

    /**
     * @dataProvider tokenProvider
     *
     * @param string $value
     * @param int $lifetime
     * @param null|string $createdAt
     * @param null|string $expirationDate
     * @param bool $hasExpired
     *
     * @return void
     */
    public function testHasExpired(
        string $value,
        int $lifetime,
        null|string $createdAt,
        null|string $expirationDate,
        bool $hasExpired
    ): void
    {
        $token = new Token([
            'value' => $value,
            'lifetime' => $lifetime,
            'created_at' => $createdAt,
            'expiration_date' => $expirationDate
        ]);

        self::assertSame($hasExpired, $token->hasExpired());
    }
}
