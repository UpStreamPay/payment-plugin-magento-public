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

namespace UpStreamPay\Core\Model\Session\Order\Builder;

use Magento\Quote\Api\Data\CartInterface;
use Magento\Quote\Model\Quote\Item;
use UpStreamPay\Core\Model\Session\Order\BuilderInterface;

/**
 * Class TaxBuilder
 *
 * @package UpStreamPay\Core\Model\Session\Order\Builder
 */
class TaxBuilder implements BuilderInterface
{
    /**
     * Build the glboal tax item element of the order.
     * It takes all the tax_rate to produce one array per tax_rate in the quote with a sum of the tax_amount for each.
     *
     * @param CartInterface $quote
     *
     * @return array
     *
     * @see UpStream Pay documentation regarding all the fields & format.
     */
    public function execute(CartInterface $quote): array
    {
        $globalTaxRate = [];
        $itemsByTaxRate = [];

        //Retrieve all the item tax & group them by tax rate.
        /** @var Item $item */
        foreach ($quote->getAllVisibleItems() as $item) {
            $taxPercent = $item->getTaxPercent();
            $taxAmount = $item->getTaxAmount();

            if ($taxPercent === 0.00 || $taxAmount === 0.00) {
                continue;
            }

            if (!isset($itemsByTaxRate[$taxPercent])) {
                $itemsByTaxRate[$taxPercent] = 0;
            }

            $itemsByTaxRate[$taxPercent] += $taxAmount;
        }

        //Build as many array as we have tax_rate.
        foreach ($itemsByTaxRate as $taxRate => $taxAmount) {
            $globalTaxRate[] = [
                'type_code' => 'vat',
                'subtype_code' => 'standard',
                'rate' => $taxRate,
                'amount' => $taxAmount,
            ];
        }

        return $globalTaxRate;
    }
}
