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
use Magento\Checkout\Model\Session;
use Magento\Quote\Api\CartRepositoryInterface;
use UpStreamPay\Core\Exception\UnsynchronizedCartAmountsException;
use Magento\Framework\Math\FloatComparator;

class PaymentInformationPlugin
{
    public function __construct(
        private readonly Session $magentoSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly FloatComparator $floatComparator
    )
    {}

    public function beforeSavePaymentInformationAndPlaceOrder(PaymentInformationManagement $subject, $cartId, $paymentMethod, $billingAddress)
    {
        $quote = $this->cartRepository->getActive($cartId);

        if (!$this->floatComparator->equal((float)$quote->getBaseGrandTotal(), (float)$this->magentoSession->getCartAmount())) {
            throw new UnsynchronizedCartAmountsException(
                'The current Session cart amount does not match the current quote amount, aborting.',
                422
            );
        }
    }
}
