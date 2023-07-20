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
use UpStreamPay\Client\Exception\NoOrderFoundException;
use UpStreamPay\Client\Model\Token\TokenService;
use UpStreamPay\Core\Exception\ConflictRetrieveTransactionsException;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Config\Source\Mode;
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
     * Get each transaction made for an order.
     *
     * @param int $orderId
     *
     * @return array
     * @throws GuzzleException
     * @throws JsonException
     * @throws NoOrderFoundException
     * @throws ConflictRetrieveTransactionsException
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
            } elseif ($exception->getCode() === 409) {
                $errorMessage = sprintf(
                    'Impossible to process upstream pay order with id %s. Please refund it in UpStream Pay BO',
                    $orderId
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
        $this->eventManager->dispatch(
            'sales_order_usp_before_capture',
            [
                'transactionId' => $transactionId,
                'body' => $body
            ]
        );

        $uri = sprintf(
            '%s%s%s%s',
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
        $uri = sprintf(
            '%s%s%s%s',
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
        $this->eventManager->dispatch(
            'sales_order_usp_before_refund',
            [
                'transactionId' => $transactionId,
                'body' => $body
            ]
        );

        $uri = sprintf(
            '%s%s%s%s',
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
            $this->logger->debug('--REQUEST BODY--');
            $this->logger->debug(print_r($body, true));
        }

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

        if ($debugMode === Debug::DEBUG_VALUE) {
            $this->logger->debug('--RESPONSE URI--');
            $this->logger->debug($uri);
            $this->logger->debug('--RESPONSE BODY--');
            $this->logger->debug(print_r($body, true));
        }

        return json_decode($rawResponse->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
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
