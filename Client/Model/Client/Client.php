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

namespace UpStreamPay\Client\Model\Client;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use JsonException;
use Psr\Log\LoggerInterface;
use UpStreamPay\Client\Exception\NoSessionFoundException;
use UpStreamPay\Client\Model\Token\TokenService;
use UpStreamPay\Core\Exception\ConflictRetrieveTransactionsException;
use UpStreamPay\Core\Model\Config;
use GuzzleHttp\ClientFactory;
use Magento\Framework\Event\ManagerInterface as EventManager;
use UpStreamPay\Core\Model\Config\Source\Debug;

/**
 * Class Client
 *
 * @package UpStreamPay\Client\Model\Client
 */
class Client implements ClientInterface
{
    private const HEADERS = 'headers';
    private const API_KEY_PARAM = 'x-api-key';
    private const QUERY = 'query';
    private const POST = 'POST';
    private const GET = 'GET';
    private const OAUTH_TOKEN_URI = '/oauth/token';
    private const CREATE_SESSION_URI = '/sessions/create';
    private const CREATE_WALLET_SESSION_URI = '/wallet/session';
    private const SESSION_URI = '/sessions/';
    private const TRANSACTIONS_URI = '/transactions/';
    private const CAPTURE_URI = '/capture';
    private const VOID_URI = '/void';
    private const REFUND_URI = '/refund';

    private const DUPLICATE_URI = '/off_session_authorize';
    private const API_URI_SKELETON = '%s%s%s%s';

    /**
     * @param ClientFactory $httpClientFactory
     * @param Config $config
     * @param TokenService $tokenService
     * @param EventManager $eventManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ClientFactory $httpClientFactory,
        private readonly Config $config,
        private readonly TokenService $tokenService,
        private readonly EventManager $eventManager,
        private readonly LoggerInterface $logger
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
        if ($this->config->getDebugMode() === Debug::DEBUG_VALUE) {
            $this->logger->debug('--GET TOKEN--');
        }

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
     */
    public function createSession(array $orderSession): array
    {
        if ($this->config->getDebugMode() === Debug::DEBUG_VALUE) {
            $this->logger->debug('--CREATE SESSION--');
        }

        $this->eventManager->dispatch('payment_usp_before_session', ['orderSession' => $orderSession]);
        $apiResponse = $this->callApi(
            $this->buildHeader(),
            $orderSession,
            self::POST,
            $this->config->getEntityId() . self::CREATE_SESSION_URI, []
        );
        $this->eventManager->dispatch(
            'payment_usp_after_session',
            [
                'orderSession' => $orderSession,
                'apiResponse' => $apiResponse
            ]
        );
        return $apiResponse;
    }

    /**
     * Create UpStream Pay wallet session.
     *
     * @param int $customerId
     *
     * @return array
     * @throws GuzzleException
     * @throws JsonException
     */
    public function createWalletSession(int $customerId): array
    {
        if ($this->config->getDebugMode() === Debug::DEBUG_VALUE) {
            $this->logger->debug('--CREATE WALLET SESSION--');
        }

        $body = ['owner_reference' => $customerId];
        $header = $this->buildHeader();
        //When creating a wallet session we have to pass an extra parameter.
        $header['x-merchant-id'] = $this->config->getMerchantId();

        $this->eventManager->dispatch('payment_usp_before_wallet_session', ['customerId' => $customerId]);
        $apiResponse = $this->callApi(
            $header,
            $body,
            self::POST,
            self::CREATE_WALLET_SESSION_URI,
            []
        );
        $this->eventManager->dispatch(
            'payment_usp_after_wallet_session',
            [
                'customerId' => $customerId,
                'apiResponse' => $apiResponse
            ]
        );

        return $apiResponse;
    }

    /**
     * Get each transaction made for a session.
     *
     * @param string $sessionId
     *
     * @return array
     * @throws GuzzleException
     * @throws JsonException
     * @throws NoSessionFoundException
     * @throws ConflictRetrieveTransactionsException
     */
    public function getAllTransactionsForSession(string $sessionId): array
    {
        $uri = sprintf(
            '%s%s%s',
            $this->config->getEntityId(),
            self::SESSION_URI,
            $sessionId
        );

        try {
            if ($this->config->getDebugMode() === Debug::DEBUG_VALUE) {
                $this->logger->debug('--GET ALL TRANSACTIONS FOR SESSION--');
            }

            return $this->callApi($this->buildHeader(), [], self::GET, $uri, []);
        } catch (GuzzleException $exception) {
            if ($exception->getCode() === 404) {
                //We most likely have no order found on UpStream Pay side.
                throw new NoSessionFoundException(
                    'There was a 404 error while trying to retrieve transactions for session ' . $sessionId
                );
            } elseif ($exception->getCode() === 409) {
                $errorMessage = sprintf(
                    'Impossible to process upstream pay session with id %s. Please refund it in UpStream Pay BO',
                    $sessionId
                );
                $this->logger->critical($errorMessage);
                $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);

                //This happens sometimes in case of a conflict, we can't even retrieve the transactions from upstream.
                throw new ConflictRetrieveTransactionsException($errorMessage);
            } else {
                $this->logger->critical(
                    'Error while trying to retrieve all transactions for the session ' . $sessionId
                );
                $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);

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
     */
    public function capture(string $transactionId, array $body): array
    {
        if ($this->config->getDebugMode() === Debug::DEBUG_VALUE) {
            $this->logger->debug('--CAPTURE TRANSACTION--');
        }

        $this->eventManager->dispatch(
            'sales_order_usp_before_capture',
            [
                'transactionId' => $transactionId,
                'body' => $body
            ]
        );

        $uri = sprintf(
            self::API_URI_SKELETON,
            $this->config->getEntityId(),
            self::TRANSACTIONS_URI,
            $transactionId,
            self::CAPTURE_URI,
        );

        $captureResponse = $this->callApi($this->buildHeader(), $body, self::POST, $uri, []);

        $this->eventManager->dispatch('sales_order_usp_after_capture', [
            'transactionId' => $transactionId,
            'body' => $body,
            'captureResponse' => $captureResponse
        ]);

        return $captureResponse;
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
     * @throws ConflictRetrieveTransactionsException
     */
    public function void(string $transactionId, array $body): array
    {
        if ($this->config->getDebugMode() === Debug::DEBUG_VALUE) {
            $this->logger->debug('--VOID TRANSACTION--');
        }

        $uri = sprintf(
            self::API_URI_SKELETON,
            $this->config->getEntityId(),
            self::TRANSACTIONS_URI,
            $transactionId,
            self::VOID_URI,
        );

        try {
            return $this->callApi($this->buildHeader(), $body, self::POST, $uri, []);
        } catch (GuzzleException $exception) {
            if ($exception->getCode() === 409) {
                $errorMessage = sprintf(
                    'Impossible to process upstream pay transaction with id %s. Please void it in UpStream Pay BO',
                    $transactionId
                );
                $this->logger->critical($errorMessage);

                //This happens sometimes in case of a conflict, we can't even retrieve the transactions from upstream.
                throw new ConflictRetrieveTransactionsException($errorMessage);
            } else {
                throw $exception;
            }
        }
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
     * @throws ConflictRetrieveTransactionsException
     */
    public function refund(string $transactionId, array $body): array
    {
        if ($this->config->getDebugMode() === Debug::DEBUG_VALUE) {
            $this->logger->debug('--REFUND TRANSACTION--');
        }

        $this->eventManager->dispatch(
            'sales_order_usp_before_refund',
            [
                'transactionId' => $transactionId,
                'body' => $body
            ]
        );

        $uri = sprintf(
            self::API_URI_SKELETON,
            $this->config->getEntityId(),
            self::TRANSACTIONS_URI,
            $transactionId,
            self::REFUND_URI,
        );

        try {
            $refundResponse = $this->callApi($this->buildHeader(), $body, self::POST, $uri, []);

            $this->eventManager->dispatch('sales_order_usp_after_refund', [
                'transactionId' => $transactionId,
                'body' => $body,
                'refundResponse' => $refundResponse
            ]);

            return $refundResponse;
        } catch (GuzzleException $exception) {
            if ($exception->getCode() === 409) {
                $errorMessage = sprintf(
                    'Impossible to process upstream pay transaction with id %s. Please refund it in UpStream Pay BO',
                    $transactionId
                );
                $this->logger->critical($errorMessage);

                //This happens sometimes in case of a conflict, we can't even retrieve the transactions from upstream.
                throw new ConflictRetrieveTransactionsException($errorMessage);
            } else {
                throw $exception;
            }
        }
    }

    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function duplicate(string $transactionId, array $body): array
    {
        if ($this->config->getDebugMode() === Debug::DEBUG_VALUE) {
            $this->logger->debug('--DUPLICATE TRANSACTION--');
        }

        $uri = sprintf(
            self::API_URI_SKELETON,
            $this->config->getEntityId(),
            self::TRANSACTIONS_URI,
            $transactionId,
            self::DUPLICATE_URI,
        );

        $duplicateResponse = $this->callApi($this->buildHeader(), $body, self::POST, $uri, []);

        return $duplicateResponse;
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
        $debugMode = $this->config->getDebugMode();
        if ($debugMode === Debug::DEBUG_VALUE) {
            $this->logger->debug('--REQUEST URI--');
            $this->logger->debug($uri);
            if ($body) {
                $this->logger->debug('--REQUEST BODY--');
                $this->logger->debug(print_r($body, true));
            }
        }

        $client = $this->httpClientFactory->create(
            [
                'config' => [
                    'base_uri' => $this->config->getModeUrl()
                ]
            ]
        );

        $options = [
            self::HEADERS => $headers,
            self::QUERY => $query,
        ];

        //Don't pass the body if there is nothing to pass, or it will create an error.
        if (!empty($body)) {
            $options[RequestOptions::JSON] = $body;
        }

        $rawResponse = $client->request($protocol, $uri, $options);
        $apiResponse = json_decode($rawResponse->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        if ($debugMode === Debug::DEBUG_VALUE) {
            $this->logger->debug('--RESPONSE URI--');
            $this->logger->debug($uri);
            if ($rawResponse->getBody()) {
                $this->logger->debug('--RESPONSE BODY--');
                $this->logger->debug(print_r($apiResponse, true));
            }
        }

        return $apiResponse;
    }

    /**
     * Build header for API request.
     *
     * @return array
     * @throws GuzzleException
     * @throws JsonException
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
