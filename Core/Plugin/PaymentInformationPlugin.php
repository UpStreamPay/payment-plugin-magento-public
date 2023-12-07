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

namespace UpStreamPay\Core\Plugin;

use Magento\Checkout\Model\PaymentInformationManagement;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;
use Magento\Quote\Model\Quote;
use UpStreamPay\Core\Exception\UnsynchronizedCartAmountsException;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Session\PurseSessionDataManager;

/**
 * Class PaymentInformationPlugin
 *
 * @package UpStreamPay\Core\Plugin
 */
class PaymentInformationPlugin
{
    /**
     * @param CartRepositoryInterface $cartRepository
     * @param PurseSessionDataManager $purseSessionDataManager
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly PurseSessionDataManager $purseSessionDataManager
    ) {
    }

    /**
     * If payment method is upstream_pay then check that the cart amount equals the upstream pay session amount.
     * @see PaymentInformationManagement::savePaymentInformationAndPlaceOrder
     *
     * @param PaymentInformationManagement $subject
     * @param $cartId
     * @param PaymentInterface $paymentMethod
     * @param AddressInterface $billingAddress
     *
     * @return void
     * @throws NoSuchEntityException
     * @throws UnsynchronizedCartAmountsException
     */
    public function beforeSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagement $subject,
        $cartId,
        PaymentInterface $paymentMethod,
        AddressInterface $billingAddress
    ): void {
        /** @var Quote $quote */
        $quote = $this->cartRepository->getActive($cartId);

        //If the payment method is upstream_pay then validate that the amount is correct. Else remove any purse data.
        if ($paymentMethod->getMethod() === Config::METHOD_CODE_UPSTREAM_PAY) {
            $this->purseSessionDataManager->validatePurseSessionAmountBeforePlaceOrder($quote, $cartId, $paymentMethod);
        } else {
            $this->purseSessionDataManager->cleanPurseSessionDataFromQuotePayment($quote);
        }
    }
}
