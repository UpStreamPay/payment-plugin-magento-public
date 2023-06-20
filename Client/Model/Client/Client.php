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

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions;
use Magento\Framework\Encryption\EncryptorInterface;
use Throwable;
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

    /**
     * @param ClientFactory $httpClientFactory
     * @param Config $config
     * @param EncryptorInterface $encryptor
     * @param TokenService $tokenService
     */
    public function __construct(
        private readonly ClientFactory $httpClientFactory,
        private readonly Config $config,
        private readonly EncryptorInterface $encryptor,
        private readonly TokenService $tokenService
    ) {}

    /**
     * Get token to authenticate on further API calls.
     *
     * @return array
     */
    public function getToken(): array
    {
        $headers = [
            'Authorization' => sprintf(
                'Basic %s',
                base64_encode($this->config->getClientId() . ':' . $this->decryptConfig($this->config->getClientSecret()))
            ),
            self::API_KEY_PARAM => $this->decryptConfig($this->config->getApiKey()),
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
     */
    public function createSession(array $orderSession): array
    {
        $token = $this->tokenService->getToken();

        if ($token->hasExpired()) {
            try {
                $token = $this->tokenService->setToken($this->getToken());
            } catch (\Exception $exception) {
                return [];
            }
        }

        $headers = [
            'Authorization' => 'Bearer ' . $token->getValue(),
            self::API_KEY_PARAM => $this->decryptConfig($this->config->getApiKey()),
            'Content-Type' => 'application/json'
        ];

        return $this->callApi($headers, $orderSession, self::POST, $this->config->getEntityId() . self::CREATE_SESSION_URI, []);
    }

    /**
     * @param int $orderId
     *
     * @return array
     * @throws \Exception
     */
    public function getAllTransactionsForOrder(int $orderId): array
    {
        $token = $this->tokenService->getToken();

        if ($token->hasExpired()) {
            try {
                $token = $this->tokenService->setToken($this->getToken());
            } catch (\Exception $exception) {
                return [];
            }
        }

        $headers = [
            'Authorization' => 'Bearer ' . $token->getValue(),
            self::API_KEY_PARAM => $this->decryptConfig($this->config->getApiKey()),
            'Content-Type' => 'application/json'
        ];

        $uri = sprintf(
            '%s%s%s',
            $this->config->getEntityId(),
            self::ORDERS_URI,
            $orderId
        );

        return $this->callApi($headers, [], self::GET, $uri, []);
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
     */
    private function callApi(array $headers, array $body, string $protocol, string $uri, array $query): array
    {
        $response = [];

        try {
            /** @var GuzzleClient $client */
            $client = $this->httpClientFactory->create(
                [
                    'config' => [
                        'base_uri' => self::API_ENDPOINT[$this->config->getMode()
                        ]
                    ]
                ]
            );

            $rawResponse = $client->request($protocol, $uri, [
                self::HEADERS => $headers,
                self::QUERY => $query,
                RequestOptions::JSON => $body,
            ]);

            $response = json_decode($rawResponse->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable $exception) {
            //TODO Remove this when error management is done. This is WIP while in early dev stages.
            throw new \Exception($exception->getMessage());
            //DEAL WITH EXCEPTION
        }

        return $response;
    }

    /**
     * Decrypt obscure configuration from database.
     *
     * @param string $config
     * @return string
     */
    private function decryptConfig(string $config): string
    {
        return $this->encryptor->decrypt(trim($config));
    }
}
