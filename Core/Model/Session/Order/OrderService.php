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

namespace UpStreamPay\Core\Model\Session\Order;

use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote;
use UpStreamPay\Core\Controller\Payment\Notification;
use UpStreamPay\Core\Controller\Payment\ReturnUrl;

/**
 * Class OrderService
 *
 * @package UpStreamPay\Core\Model\Session\Order
 */
class OrderService
{
    /**
     * @param BuilderInterface[] $builders
     * @param UrlInterface $url
     */
    public function __construct(
        private readonly array $builders,
        private readonly UrlInterface $url
    ) {
    }

    /**
     * Build the order that UpStream Pay needs to create a session.
     *
     * @param Quote $quote
     *
     * @return array
     *
     * @see UpStream Pay documentation regarding all the fields & format.
     */
    public function execute(Quote $quote): array
    {
        $order = [];

        $netAmount = $quote->getBaseSubtotalWithDiscount() + $quote->getShippingAddress()->getBaseShippingAmount();

        //$order['hook'] = $this->url->getUrl(Notification::URL_PATH);
        $order['hook'] = 'https://4254-193-238-148-139.ngrok-free.app/upstreampay/payment/notification';
        $order['amount'] = $quote->getBaseGrandTotal();
        $order['order']['redirection'] = $this->url->getUrl(ReturnUrl::URL_PATH);
        $order['order']['reference'] = $quote->getId();
        $order['order']['amount'] = $quote->getBaseGrandTotal();
        $order['order']['net_amount'] = $netAmount;
        $order['order']['tax_amount'] = $quote->getBaseGrandTotal() - $netAmount;
        $order['order']['currency_code'] = $quote->getBaseCurrencyCode();

        /** @var BuilderInterface $builder */
        foreach ($this->builders as $builderName => $builder) {
            $order['order'][$builderName] = $builder->execute($quote);
        }

        return $order;
    }
}
