<?php
/**
 * UpStream Pay
 *
 * Copyright (c) 2019-2023 UpStream Pay.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Claranet France <info@fr.clara.net>
 */
declare(strict_types=1);

namespace UpStreamPay\Client\Model\Client;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use JsonException;
use UpStreamPay\Client\Exception\NoOrderFoundException;
use UpStreamPay\Client\Exception\TokenValidatorException;
use UpStreamPay\Client\Model\Token\TokenService;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Config\Source\Mode;
use GuzzleHttp\ClientFactory;

/**
 * Class Client
 *
 * @package UpStreamPay\Client\Model\Client
 */
class Client implements ClientInterface
{
    private const API_ENDPOINT = [
        Mode::SANDBOX_VALUE => 'https://api.preprod.upstreampay.com',
        Mode::PRODUCTION_VALUE => ''
    ];

    private const HEADERS = 'headers';
    private const API_KEY_PARAM = 'x-api-key';
    private const QUERY = 'query';
    private const POST = 'POST';
    private const GET = 'GET';
    private const OAUTH_TOKEN_URI = '/oauth/token';
    private const CREATE_SESSION_URI = '/sessions/create';
    private const ORDERS_URI = '/orders/';
    private const TRANSACTIONS_URI = '/transactions/';
    private const CAPTURE_URI = '/capture';
    private const VOID_URI = '/void';
    private const REFUND_URI = '/refund';

    /**
     * @param ClientFactory $httpClientFactory
     * @param Config $config
     * @param TokenService $tokenService
     */
    public function __construct(
        private readonly ClientFactory $httpClientFactory,
        private readonly Config $config,
        private readonly TokenService $tokenService
    ) {}

    /**
     * Get token to authenticate on further API calls.
     *
     * @return array
     * @throws GuzzleException
     * @throws JsonException
     */
    public function getToken(): array
    {
        $headers = [
            'Authorization' => sprintf(
                'Basic %s',
                base64_encode($this->config->getClientId() . ':' . $this->config->getClientSecret())
            ),
            self::API_KEY_PARAM => $this->config->getApiKey(),
            'Content-Type' => 'application/x-www-form-urlencoded'
        ];

        $query = [
            'grant_type' => 'client_credentials',
        ];

        return $this->callApi($headers, [], self::POST, self::OAUTH_TOKEN_URI, $query);
    }

    /**
     * Create UpStream Pay session.
     *
     * @param array $orderSession
     *
     * @return array
     * @throws GuzzleException
     * @throws JsonException
     * @throws TokenValidatorException
     */
    public function createSession(array $orderSession): array
    {
        return $this->callApi(
            $this->buildHeader(),
            $orderSession,
            self::POST,
            $this->config->getEntityId() . self::CREATE_SESSION_URI, []
        );
    }

    /**
     * Get each transaction made for an order.
     *
     * @param int $orderId
     *
     * @return array
     * @throws GuzzleException
     * @throws JsonException
     * @throws NoOrderFoundException
     * @throws TokenValidatorException
     */
    public function getAllTransactionsForOrder(int $orderId): array
    {
        $uri = sprintf(
            '%s%s%s',
            $this->config->getEntityId(),
            self::ORDERS_URI,
            $orderId
        );

        try {
            return $this->callApi($this->buildHeader(), [], self::GET, $uri, []);
        } catch (GuzzleException $exception) {
            if ($exception->getCode() === 404) {
                //We most likely have no order found on UpStream Pay side.
                throw new NoOrderFoundException(
                    'There was a 404 error while trying to retrieve transactions for order ' . $orderId
                );
            } else {
                throw $exception;
            }
        }
    }

    /**
     * Capture the given transaction through transaction ID & body parameters.
     *
     * The body contains the amount to capture:
     * {
     *      "order": {
     *          "amount": 271.92,
     *          "currency_code": "EUR"
     *      },
     *      "amount": 270.92
     * }
     *
     * @param string $transactionId
     * @param array $body
     *
     * @return array
     * @throws GuzzleException
     * @throws JsonException
     * @throws TokenValidatorException
     */
    public function capture(string $transactionId, array $body): array
    {
        $uri = sprintf(
            '%s%s%s%s',
            $this->config->getEntityId(),
            self::TRANSACTIONS_URI,
            $transactionId,
            self::CAPTURE_URI,
        );

        return $this->callApi($this->buildHeader(), $body, self::POST, $uri, []);
    }

    /**
     * Void the given transaction through transaction ID & body parameters.
     *
     * The body contains the amount to void:
     * {
     *      "order": {
     *          "amount": 271.92,
     *          "currency_code": "EUR"
     *      },
     *      "amount": 270.92
     * }
     *
     * @param string $transactionId
     * @param array $body
     *
     * @return array
     * @throws GuzzleException
     * @throws JsonException
     * @throws TokenValidatorException
     */
    public function void(string $transactionId, array $body): array
    {
        $uri = sprintf(
            '%s%s%s%s',
            $this->config->getEntityId(),
            self::TRANSACTIONS_URI,
            $transactionId,
            self::VOID_URI,
        );

        return $this->callApi($this->buildHeader(), $body, self::POST, $uri, []);
    }

    /**
     * Refund the given transaction through transaction ID & body parameters.
     *
     * The body contains the amount to refund:
     * {
     *      "order": {
     *          "amount": 271.92,
     *          "currency_code": "EUR"
     *      },
     *      "amount": 270.92
     * }
     *
     * @param string $transactionId
     * @param array $body
     *
     * @return array
     * @throws GuzzleException
     * @throws JsonException
     * @throws TokenValidatorException
     */
    public function refund(string $transactionId, array $body): array
    {
        $uri = sprintf(
            '%s%s%s%s',
            $this->config->getEntityId(),
            self::TRANSACTIONS_URI,
            $transactionId,
            self::REFUND_URI,
        );

        return $this->callApi($this->buildHeader(), $body, self::POST, $uri, []);
    }

    /**
     * Call the API to perform a request.
     *
     * @param array $headers
     * @param array $body
     * @param string $protocol
     * @param string $uri
     * @param array $query
     *
     * @return array
     * @throws GuzzleException
     * @throws JsonException
     */
    private function callApi(array $headers, array $body, string $protocol, string $uri, array $query): array
    {
        $client = $this->httpClientFactory->create(
            [
                'config' => [
                    'base_uri' => self::API_ENDPOINT[$this->config->getMode()
                    ]
                ]
            ]
        );

        $options = [
            self::HEADERS => $headers,
            self::QUERY => $query,
        ];

        //Don't pass the body if there is nothing to pass, or it will create an error.
        if (count($body) > 0) {
            $options[RequestOptions::JSON] = $body;
        }

        $rawResponse = $client->request($protocol, $uri, $options);
        return json_decode($rawResponse->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Build header for API request.
     *
     * @return array
     * @throws GuzzleException
     * @throws JsonException
     * @throws TokenValidatorException
     */
    private function buildHeader(): array
    {
        $token = $this->tokenService->getToken();

        if ($token->hasExpired()) {
            $token = $this->tokenService->setToken($this->getToken());
        }

        return [
            'Authorization' => 'Bearer ' . $token->getValue(),
            self::API_KEY_PARAM => $this->config->getApiKey(),
            'Content-Type' => 'application/json'
        ];
    }
}
