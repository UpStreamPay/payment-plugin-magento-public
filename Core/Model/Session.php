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

namespace UpStreamPay\Core\Model;

use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Psr\Log\LoggerInterface;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Api\SessionInterface;
use UpStreamPay\Core\Model\Session\Order\OrderService;

/**
 * Class Session
 *
 * @package UpStreamPay\Core\Model
 */
class Session implements SessionInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly OrderService    $orderService,
        private readonly CheckoutSession $checkoutSession,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Return the session data (build the order) in order to indicate to UpStream Pay what payment methods to return.
     *
     * @return array
     */
    public function getSession(): array
    {
        try {
            $quote = $this->checkoutSession->getQuote();

            if (!$quote || !$quote->getId()) {
                throw new Exception(
                    'Tried to retrieve a quote to create UpStream Pay session but it appears it is invalid.'
                );
            }
        } catch (Exception $exception) {
            $this->logger->critical(
                sprintf(
                'Error getting UpStream Pay session. Impossible to get current customer quote because %s',
                    $exception->getMessage()
                ),
                ['exception' => $exception->getTraceAsString()]
            );

            return [];
        }

        try {
            $order = $this->orderService->execute($quote);
        } catch (Exception $exception) {
            $this->logger->critical(
                sprintf(
                'Error while creating order to init UpStream Pay session for quote with id %s.',
                    $exception->getMessage(),
                ),
                ['exception' => $exception->getTraceAsString()]
            );

            return [];
        }

        $response = $this->client->createSession($order);

        return [$response];
    }
}
