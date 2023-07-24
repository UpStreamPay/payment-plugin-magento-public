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

namespace UpStreamPay\Core\Model\Session\Order\Builder;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\Information;
use Magento\Store\Model\ScopeInterface;
use UpStreamPay\Core\Model\Session\Order\AddressBuilderInterface;
use UpStreamPay\Core\Model\Session\Order\BuilderInterface;
use Magento\Framework\Event\ManagerInterface as EventManager;

/**
 * Class ShipmentBuilder
 *
 * @package UpStreamPay\Core\Model\Session\Order\Builder
 */
class ShipmentBuilder implements BuilderInterface
{
    /**
     * @param BuilderInterface[] $builders
     * @param AddressBuilderInterface $addressBuilder
     * @param EventManager $eventManager
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
       private readonly array $builders,
       private readonly AddressBuilderInterface $addressBuilder,
       private readonly EventManager $eventManager,
       private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Build the shipment data for the UpStream Pay order.
     *
     * @param CartInterface $quote
     *
     * @return array[]
     *
     * @see UpStream Pay documentation regarding all the fields & format.
     */
    public function execute(CartInterface $quote): array
    {
        $this->eventManager->dispatch('sales_order_usp_before_shipment', ['quote' => $quote]);

        $shipmentData = [];
        $shipmentData['delivery_type_code'] = $quote->getIsVirtual() ? 'digital' : 'user_delivery';
        $shipmentData['seller_reference'] = $this->scopeConfig->getValue(
            Information::XML_PATH_STORE_INFO_NAME,
            ScopeInterface::SCOPE_STORE
        );

        if (!$quote->getIsVirtual()) {
            $shipmentData['amount'] = $quote->getShippingAddress()->getBaseShippingInclTax();
            $shipmentData['net_amount'] = $quote->getShippingAddress()->getBaseShippingAmount();
            $shipmentData['tax_amount'] = $quote->getShippingAddress()->getBaseShippingTaxAmount();
            $shipmentData['delivery_method_reference'] = $quote->getShippingAddress()->getShippingMethod();
        }

        //In case of a virtual quote, the shipping address element will only contain the customer email address.
        $shipmentData['shipping_address'] = $this->addressBuilder->execute(
            $quote,
            AddressBuilderInterface::SHIPPING_ADDRESS
        );

        /** @var BuilderInterface $builder */
        foreach ($this->builders as $builderName => $builder) {
            $shipmentData[$builderName] = $builder->execute($quote);
        }

        $this->eventManager->dispatch(
            'sales_order_usp_after_shipment',
            [
                'quote' => $quote,
                'shipmentData' => $shipmentData
            ]
        );

        return [$shipmentData];
    }
}
