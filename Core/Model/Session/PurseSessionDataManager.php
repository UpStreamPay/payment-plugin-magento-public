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

namespace UpStreamPay\Core\Model\Session;

use Magento\Framework\Math\FloatComparator;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Exception\UnsynchronizedCartAmountsException;

/**
 * Class PurseSessionDataManager
 *
 * @package UpStreamPay\Core\Model\Session
 */
class PurseSessionDataManager
{
    public const QUOTE_PAYMENT_PURSE_SESSION_AMOUNT_KEY = 'purse_session_amount';
    public const PAYMENT_PURSE_SESSION_ID = 'purse_session_id';

    /**
     * @param CartRepositoryInterface $cartRepository
     * @param FloatComparator $floatComparator
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly FloatComparator $floatComparator,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Validate purse
     *
     * @param Quote $quote
     * @param int $cartId
     *
     * @return void
     * @throws UnsynchronizedCartAmountsException
     */
    public function validatePurseSessionAmountBeforePlaceOrder(Quote $quote, int $cartId): void
    {
        if (!$this->floatComparator->equal(
            (float)$quote->getBaseGrandTotal(),
            (float)$quote->getPayment()->getData(self::QUOTE_PAYMENT_PURSE_SESSION_AMOUNT_KEY)
        )) {
            $errorMessage = sprintf(
                'The cart amount is %s & the purse session amount is %s. The cart ID is %s',
                $quote->getBaseGrandTotal(),
                $quote->getPayment()->getData(self::QUOTE_PAYMENT_PURSE_SESSION_AMOUNT_KEY),
                $cartId
            );

            $this->logger->critical(
                'Impossible to place the order, cart amount & purse session amount are different. ' . $errorMessage
            );

            throw new UnsynchronizedCartAmountsException(
                'The current Session cart amount does not match the current quote amount, aborting.',
                422
            );
        }
    }

    /**
     * Remove any purse session data on quote payment if the payment is different from upstream_pay.
     *
     * @param Quote $quote
     *
     * @return void
     */
    public function cleanPurseSessionDataFromQuotePayment(Quote $quote): void
    {
        //If the payment is not upstream_pay then remove any custom purse data.
        if ($this->quotePaymentHasPurseData($quote)) {
            $quote->getPayment()
                ->setData(self::QUOTE_PAYMENT_PURSE_SESSION_AMOUNT_KEY, 0.00)
                ->setData(self::PAYMENT_PURSE_SESSION_ID, null)
            ;

            $this->cartRepository->save($quote);
        }
    }

    /**
     * Set all the purse data needed on the quote.
     *
     * @param array $sessionData
     * @param Quote $quote
     *
     * @return void
     */
    public function setPurseSessionDataInQuote(array $sessionData, Quote $quote): void
    {
        $quote->getPayment()
            ->setData(self::QUOTE_PAYMENT_PURSE_SESSION_AMOUNT_KEY, $sessionData['amount'])
            ->setData(self::PAYMENT_PURSE_SESSION_ID, $sessionData['id'])
        ;

        $this->cartRepository->save($quote);
    }

    /**
     * Return true if the quote payment has purse session data.
     *
     * @param Quote $quote
     *
     * @return bool
     */
    private function quotePaymentHasPurseData(Quote $quote): bool
    {
        return $quote->getPayment()->getData(self::PAYMENT_PURSE_SESSION_ID) !== null;
    }
}
