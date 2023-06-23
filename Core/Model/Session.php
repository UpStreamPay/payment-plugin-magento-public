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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
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
    /**
     * @param ClientInterface $client
     * @param OrderService $orderService
     * @param CheckoutSession $checkoutSession
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly OrderService $orderService,
        private readonly CheckoutSession $checkoutSession,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Return the session data (build the order) in order to indicate to UpStream Pay what payment methods to return.
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getSession(): array
    {
        $quote = $this->checkoutSession->getQuote();

        if (!$quote || !$quote->getId()) {
            $errorMessage = 'Tried to retrieve a quote to create UpStream Pay session but it appears it is invalid.';
            $this->logger->critical($errorMessage);

            throw new Exception($errorMessage);
        }

        $order = $this->orderService->execute($quote);
        $response = $this->client->createSession($order);

        return [$response];
    }
}
