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

namespace UpStreamPay\Client\Test\Model\Client;

use Exception;
use Generator;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JsonException;
use Magento\Framework\Event\ManagerInterface as EventManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use UpStreamPay\Client\Exception\NoOrderFoundException;
use UpStreamPay\Client\Model\Client\Client;
use UpStreamPay\Client\Model\Token\Token;
use UpStreamPay\Client\Model\Token\TokenService;
use UpStreamPay\Core\Exception\ConflictRetrieveTransactionsException;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Config\Source\Debug;

/**
 * Class ClientTest
 *
 * @package UpStreamPay\Client\Model\Client
 */
class ClientTest extends TestCase
{
    private const HEADERS = [
        'Authorization' => 'Bearer fakeAccessToken',
        'x-api-key' => 'fakeApiKey',
        'Content-Type' => 'application/json',
    ];

    private Config $config;
    private TokenService $tokenService;
    private LoggerInterface $logger;
    private Client $client;
    private GuzzleClient $httpClient;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->config = self::createMock(Config::class);
        $this->tokenService = self::createMock(TokenService::class);
        $this->logger = self::createMock(LoggerInterface::class);
        $this->httpClient = self::createMock(GuzzleClient::class);
        $clientFactory = self::createMock(ClientFactory::class);

        $clientFactory->expects(self::any())
            ->method('create')
            ->willReturn($this->httpClient);

        $this->config->expects(self::any())
            ->method('getEntityId')
            ->willReturn('fakeEntityId');

        $this->config->expects(self::any())
            ->method('getDebugMode')
            ->willReturn(Debug::DEBUG_VALUE);

        $this->config->expects(self::any())
            ->method('getClientId')
            ->willReturn('fakeClientId');

        $this->config->expects(self::any())
            ->method('getClientSecret')
            ->willReturn('fakeClientSecret');

        $this->config->expects(self::any())
            ->method('getApiKey')
            ->willReturn('fakeApiKey');

        $this->tokenService->expects(self::any())
            ->method('getToken')
            ->willReturn(new Token([
                'value' => 'fakeAccessToken',
                'lifetime' => 1440,
                'created_at' => '1900-12-12 00:00:00',
                'expiration_date' => '1900-12-13 00:00:00'
            ]));

        $this->client = new Client(
            $clientFactory,
            $this->config,
            $this->tokenService,
            self::createMock(EventManager::class),
            $this->logger
        );
    }

    /**
     * Test the refund function with no errors.
     *
     * @return void
     * @throws ConflictRetrieveTransactionsException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testRefund(): void
    {
        $body = [
            'order' => [
                'amount' => 271.92,
                'currency_code' => 'EUR',
            ],
            'amount' => 270.92,
        ];
        $expectedResponseData = [
            'method' => 'paypal',
            'session_id' => '45687897799798',
            'transaction_id' => '123456789',
            'status' => 'SUCCESS'
        ];

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with('POST', 'fakeEntityId/transactions/123456789/refund', [
                'headers' => self::HEADERS,
                'json' => $body,
                'query' => [],
            ])
            ->willReturn(new Response(200, [], json_encode($expectedResponseData)));

        $result = $this->client->refund('123456789', $body);

        self::assertEquals($expectedResponseData, $result);
    }

    /**
     * Test the refund function with all the possible exception.
     *
     * @dataProvider refundExceptionDataProvider
     *
     * @param int $responseCode
     * @param string $expectedException
     * @param string $errorMessage
     *
     * @return void
     * @throws ConflictRetrieveTransactionsException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testRefundException(int $responseCode, string $expectedException, string $errorMessage): void
    {
        $body = [
            'order' => [
                'amount' => 271.92,
                'currency_code' => 'EUR',
            ],
            'amount' => 270.92,
        ];

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with('POST', 'fakeEntityId/transactions/123456789/refund', [
                'headers' => self::HEADERS,
                'json' => $body,
                'query' => [],
            ])
            ->willThrowException(new RequestException(
                    $errorMessage,
                    new Request('GET', '/'),
                    new Response($responseCode))
            );

        $this->logger->expects(self::atMost(1))
            ->method('critical')
            ->with($errorMessage);

        $this->expectException($expectedException);
        $this->client->refund('123456789', $body);
    }

    /**
     * @return Generator
     */
    public function refundExceptionDataProvider(): Generator
    {
        yield 'Conflict exception' => [
            'responseCode' => 409,
            'expectedException' => ConflictRetrieveTransactionsException::class,
            'errorMessage' => 'Impossible to process upstream pay transaction with id 123456789. Please refund it in UpStream Pay BO'
        ];

        yield 'Generic exception' => [
            'responseCode' => 500,
            'expectedException' => Exception::class,
            'errorMessage' => 'Error while trying refund the order.'
        ];
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testCreateSession(): void
    {
        $orderSession = [
            'customerId' => '12'
        ];

        $expectedResponseData = [
            'session_id' => 'fakeSessionId',
        ];

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with('POST', 'fakeEntityId/sessions/create', [
                'headers' => self::HEADERS,
                'query' => [],
                'json' => $orderSession,
            ])
            ->willReturn(new Response(200, [], json_encode($expectedResponseData)));

        $apiResponse = $this->client->createSession($orderSession);

        self::assertEquals($expectedResponseData, $apiResponse);
    }

    /**
     * Test the getAllTransactionsForOrder with no exception.
     *
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     * @throws NoOrderFoundException
     * @throws ConflictRetrieveTransactionsException
     */
    public function testGetAllTransactionsForOrderSuccess(): void
    {
        $transaction = [
            'method' => 'paypal',
            'session_id' => '45687897799798',
            'transaction_id' => '989865112',
            'status' => 'SUCCESS'
        ];

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with('GET', 'fakeEntityId/orders/123', [
                'headers' => self::HEADERS,
                'query' => [],
            ])
            ->willReturn(new Response(200, [], json_encode($transaction)));

        $result = $this->client->getAllTransactionsForOrder(123);

        self::assertEquals($transaction, $result);
    }

    /**
     * Test the getAllTransactionsForOrder function with all the possible exception.
     *
     * @dataProvider getAllTransactionsExceptionDataProvider
     *
     * @param int $responseCode
     * @param string $expectedException
     * @param string $errorMessage
     *
     * @throws ConflictRetrieveTransactionsException
     * @throws GuzzleException
     * @throws JsonException
     * @throws NoOrderFoundException
     */
    public function testGetAllTransactionsForOrderException(
        int $responseCode,
        string $expectedException,
        string $errorMessage
    ): void
    {
        $this->httpClient->expects(self::once())
            ->method('request')
            ->with('GET', 'fakeEntityId/orders/123', [
                'headers' => self::HEADERS,
                'query' => [],
            ])
            ->willThrowException(new RequestException(
                $errorMessage,
                new Request('GET', '/'),
                new Response($responseCode))
            );

        $this->logger->expects(self::atMost(4))
            ->method('critical')
            ->with($errorMessage);

        $this->expectException($expectedException);

        $this->client->getAllTransactionsForOrder(123);
    }

    /**
     * @return Generator
     */
    public function getAllTransactionsExceptionDataProvider(): Generator
    {
        yield 'No order found exception' => [
            'responseCode' => 404,
            'expectedException' => NoOrderFoundException::class,
            'errorMessage' => 'There was a 404 error while trying to retrieve transactions for order 123'
        ];

        yield 'Conflict exception' => [
            'responseCode' => 409,
            'expectedException' => ConflictRetrieveTransactionsException::class,
            'errorMessage' => 'Impossible to process upstream pay order with id 123. Please refund it in UpStream Pay BO'
        ];

        yield 'Generic exception' => [
            'responseCode' => 500,
            'expectedException' => Exception::class,
            'errorMessage' => 'Error while trying to retrieve all transactions for the order.'
        ];
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testGetToken(): void
    {
        $clientId = 'fakeClientId';
        $clientSecret = 'fakeClientSecret';
        $apiKey = 'fakeApiKey';
        $base64Auth = base64_encode("$clientId:$clientSecret");

        $expectedHeaders = [
            'Authorization' => "Basic $base64Auth",
            'x-api-key' => $apiKey,
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $expectedQuery = ['grant_type' => 'client_credentials'];

        $expectedResponseData = [
            'access_token' => 'yourAccessToken',
            'token_type' => 'bearer',
            'expires_in' => 1440,
        ];

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with('POST', '/oauth/token', [
                'headers' => $expectedHeaders,
                'query' => $expectedQuery,
            ])
            ->willReturn(new Response(200, [], json_encode($expectedResponseData)));

        $token = $this->client->getToken();

        self::assertEquals($expectedResponseData, $token);
    }

    /**
     * @return void
     * @throws ConflictRetrieveTransactionsException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testVoid(): void
    {
        $body = [
            'order' => [
                'amount' => 271.92,
                'currency_code' => 'EUR',
            ],
            'amount' => 270.92,
        ];
        $expectedResponseData = [
            'method' => 'paypal',
            'session_id' => '45687897799798',
            'transaction_id' => '123456789',
            'status' => 'SUCCESS'
        ];

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with('POST', 'fakeEntityId/transactions/123456789/void', [
                'headers' => self::HEADERS,
                'json' => $body,
                'query' => [],
            ])
            ->willReturn(new Response(200, [], json_encode($expectedResponseData)));

        $result = $this->client->void('123456789', $body);

        self::assertEquals($expectedResponseData, $result);
    }

    /**
     * @dataProvider voidExceptionDataProvider
     *
     * @param int $responseCode
     * @param string $expectedException
     * @param string $errorMessage
     *
     * @return void
     * @throws ConflictRetrieveTransactionsException
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testVoidException(int $responseCode, string $expectedException, string $errorMessage): void
    {
        $body = [
            'order' => [
                'amount' => 271.92,
                'currency_code' => 'EUR',
            ],
            'amount' => 270.92,
        ];

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with('POST', 'fakeEntityId/transactions/123456789/void', [
                'headers' => self::HEADERS,
                'json' => $body,
                'query' => [],
            ])
            ->willThrowException(new RequestException(
                    $errorMessage,
                    new Request('GET', '/'),
                    new Response($responseCode))
            );

        $this->logger->expects(self::any())
            ->method('critical')
            ->with($errorMessage);

        $this->expectException($expectedException);
        $this->client->void('123456789', $body);
    }

    /**
     * @return Generator
     */
    public function voidExceptionDataProvider(): Generator
    {
        yield 'Conflict exception' => [
            'responseCode' => 409,
            'expectedException' => ConflictRetrieveTransactionsException::class,
            'errorMessage' => 'Impossible to process upstream pay transaction with id 123456789. Please void it in UpStream Pay BO'
        ];

        yield 'Generic exception' => [
            'responseCode' => 500,
            'expectedException' => Exception::class,
            'errorMessage' => 'Error while trying to void transactions for the order.'
        ];
    }

    /**
     * @return void
     * @throws GuzzleException
     * @throws JsonException
     */
    public function testCapture(): void
    {
        $body = [
            'order' => [
                'amount' => 271.92,
                'currency_code' => 'EUR',
            ],
            'amount' => 270.92,
        ];
        $expectedResponseData = [
            'method' => 'paypal',
            'session_id' => '45687897799798',
            'transaction_id' => '123456789',
            'status' => 'SUCCESS'
        ];

        $this->httpClient->expects(self::once())
            ->method('request')
            ->with('POST', 'fakeEntityId/transactions/123456789/capture', [
                'headers' => self::HEADERS,
                'json' => $body,
                'query' => [],
            ])
            ->willReturn(new Response(200, [], json_encode($expectedResponseData)));

        $result = $this->client->capture('123456789', $body);

        self::assertEquals($expectedResponseData, $result);
    }
}
