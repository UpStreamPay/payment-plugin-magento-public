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

namespace UpStreamPay\Core\Test\Model\Session\Order\Builder;

use Magento\Catalog\Model\Product\Type;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use PHPUnit\Framework\MockObject\MockObject;
use UpStreamPay\Core\Model\Session\Order\Builder\ItemBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Class ItemBuilderTest
 *
 * @package UpStreamPay\Core\Test\Model\Session\Order\Builder
 */
class ItemBuilderTest extends TestCase
{
    private Quote&MockObject $quoteMock;
    private ItemBuilder $itemBuilder;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        //Because of magic methods.
        $methods = array_merge(
            get_class_methods(Item::class),
            ['getBasePrice', 'getBaseRowTotal', 'getBaseDiscountAmount', 'getBaseTaxAmount', 'getTaxPercent']
        );
        $this->quoteMock = self::createMock(Quote::class);

        $quoteItemMock = self::createPartialMock(Item::class, $methods);
        $quoteItemMock->method('getProductType')
            ->willReturn(Type::TYPE_VIRTUAL)
        ;
        $quoteItemMock->method('getSku')
            ->willReturn('sku')
        ;
        $quoteItemMock->method('getName')
            ->willReturn('productName')
        ;
        $quoteItemMock->method('getBasePrice')
            ->willReturn(10.00)
        ;
        $quoteItemMock->method('getQty')
            ->willReturn(1)
        ;
        $quoteItemMock->method('getBaseRowTotal')
            ->willReturn(10.00)
        ;
        $quoteItemMock->method('getBaseDiscountAmount')
            ->willReturn(0.00)
        ;
        $quoteItemMock->method('getBaseTaxAmount')
            ->willReturn(1.00)
        ;
        $quoteItemMock->method('getTaxPercent')
            ->willReturn(20.00)
        ;

        $this->quoteMock->method('getAllVisibleItems')
            ->willReturn([$quoteItemMock])
        ;

        $this->itemBuilder = new ItemBuilder();
    }

    /**
     * @return void
     */
    public function testExecute(): void
    {
        $expectedItemData[] = [
            'type_code' => 'digital',
            'sku_reference' => 'sku',
            'name' => 'productName',
            'price' => 10.00,
            'quantity' => 1,
            'amount' => 11.00,
            'tax_lines' => [
                0 => [
                    'type_code' => 'vat',
                    'subtype_code' => 'standard',
                    'rate' => 20.00,
                    'amount' => 1.00
                ]
            ]
        ];

        self::assertSame($expectedItemData, $this->itemBuilder->execute($this->quoteMock));
    }
}
