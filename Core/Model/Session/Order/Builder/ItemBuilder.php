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

use Magento\Catalog\Model\Product\Type;
use Magento\Quote\Api\Data\CartInterface;
use UpStreamPay\Core\Model\Session\Order\BuilderInterface;

/**
 * Class ItemBuilder
 *
 * @package UpStreamPay\Core\Model\Session\Order\Builder
 */
class ItemBuilder implements BuilderInterface
{
    /**
     * Build the item data for the UpStream Pay order.
     *
     * @param CartInterface $quote
     *
     * @return array
     *
     * @see UpStream Pay documentation regarding all the fields & format.
     */
    public function execute(CartInterface $quote): array
    {
        $itemsData = [];

        foreach ($quote->getAllVisibleItems() as $item) {
            $itemsData[] = [
                'type_code' => $item->getProductType() === Type::TYPE_VIRTUAL ? 'digital' : 'product',
                'sku_reference' => $item->getSku(),
                'name' => $item->getName(),
                'price' => $item->getPrice(),
                'quantity' => $item->getQty(),
                'amount' => $item->getRowTotal() - $item->getDiscountAmount() + $item->getTaxAmount(),
                'tax_lines' => [
                    0 => [
                        'type_code' => 'vat',
                        'subtype_code' => 'standard',
                        'rate' => $item->getTaxPercent(),
                        'amount' => $item->getTaxAmount()
                    ]
                ]
            ];
        }

        return $itemsData;
    }
}
