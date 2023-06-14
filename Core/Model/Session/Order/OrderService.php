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
     * @param Quote $quote
     *
     * @return array
     */
    public function execute(Quote $quote): array
    {
        $order = [];

        $netAmount = $quote->getSubtotal() + $quote->getShippingAddress()->getShippingAmount();

        $order['hook'] = $this->url->getUrl(Notification::URL_PATH);
        $order['amount'] = $quote->getGrandTotal();
        $order['order']['redirection'] = $this->url->getUrl(ReturnUrl::URL_PATH);
        $order['order']['reference'] = $quote->getId();
        $order['order']['amount'] = $quote->getGrandTotal();
        $order['order']['net_amount'] = $netAmount;
        $order['order']['tax_amount'] = $quote->getGrandTotal() - $netAmount;
        $order['order']['currency_code'] = $quote->getQuoteCurrencyCode();

        /** @var BuilderInterface $builder */
        foreach ($this->builders as $builderName => $builder) {
            $order['order'][$builderName] = $builder->execute($quote);
        }

        return $order;
    }
}
