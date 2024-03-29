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

namespace UpStreamPay\Core\Model;

use Exception;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Psr\Log\LoggerInterface;
use Throwable;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Api\SessionInterface;
use UpStreamPay\Core\Exception\CreateSessionException;
use UpStreamPay\Core\Model\Session\Order\OrderService;
use UpStreamPay\Core\Model\Session\PurseSessionDataManager;

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
     * @param PaymentMethod $paymentMethod
     * @param PurseSessionDataManager $purseSessionDataManager
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly OrderService $orderService,
        private readonly CheckoutSession $checkoutSession,
        private readonly LoggerInterface $logger,
        private readonly PaymentMethod $paymentMethod,
        private readonly PurseSessionDataManager $purseSessionDataManager
    ) {
    }

    /**
     * Return the session data (build the order) in order to indicate to UpStream Pay what payment methods to return.
     *
     * @param string $guestEmail
     *
     * @return array
     * @throws CreateSessionException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function getSession(string $guestEmail = ''): array
    {
        $quote = $this->checkoutSession->getQuote();

        if (!$quote || !$quote->getId()) {
            $errorMessage = 'Tried to retrieve a quote to create UpStream Pay session but it appears it is invalid.';
            $this->logger->critical($errorMessage);

            throw new CreateSessionException($errorMessage);
        }

        //If the customer is a guest, set the guest email right away in temp property.
        if ($quote->getCustomerIsGuest()) {
            $quote->setData('guest_email', $guestEmail);
        }

        $order = $this->orderService->execute($quote);

        try {
            $response = $this->client->createSession($order);
        } catch (Throwable $exception) {
            $this->logger->critical('Error while creating the session, widget cannot be displayed.');
            $this->logger->critical($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);

            throw new CreateSessionException($exception->getMessage());
        }

        //Save payment method from session in DB.
        foreach ($response['protocols'] as $partnerName => $method) {
            foreach ($method as $methodName => $methodValues) {
                $labels = $methodValues['configurations']['labels'];

                if (in_array(PaymentMethod::PRIMARY, $labels)) {
                    $type = PaymentMethod::PRIMARY;
                } elseif (in_array(PaymentMethod::SECONDARY, $labels)) {
                    $type = PaymentMethod::SECONDARY;
                }

                $paymentMethodName = $partnerName . ' / ' . $methodName;

                $this->paymentMethod->updateOrCreate($paymentMethodName, $type);
            }
        }

        //We have to save the purse session amount in quote payment in order to reuse it later.
        $this->purseSessionDataManager->setPurseSessionDataInQuote($response, $quote);

        return [$response];
    }
}
