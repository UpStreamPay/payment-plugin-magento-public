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

use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use PHPUnit\Framework\MockObject\MockObject;
use UpStreamPay\Core\Model\Session\Order\Builder\TaxBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Class TaxBuilderTest
 *
 * @package UpStreamPay\Core\Test\Model\Session\Order\Builder
 */
class TaxBuilderTest extends TestCase
{
    private Quote&MockObject $quoteMock;
    private Item&MockObject $allVisibleQuoteItemMock;
    private TaxBuilder $taxBuilder;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        //Because of magic methods.
        $methods = array_merge(
            get_class_methods(Item::class),
            ['getBaseTaxAmount', 'getTaxPercent', 'getParentItemId']
        );

        $allItemMock = self::createPartialMock(Item::class, $methods);
        $allItemMock->method('getParentItemId')
            ->willReturn(123)
        ;
        $allItemMock->method('getTaxPercent')
            ->willReturn(20.00)
        ;
        $allItemMock->method('getBaseTaxAmount')
            ->willReturn(10.00)
        ;

        $this->allVisibleQuoteItemMock = self::createPartialMock(Item::class, $methods);
        $this->allVisibleQuoteItemMock->method('getId')
            ->willReturn(123)
        ;
        $this->allVisibleQuoteItemMock->method('getTaxPercent')
            ->willReturn(0.00)
        ;
        $this->allVisibleQuoteItemMock->method('getBaseTaxAmount')
            ->willReturn(0.00)
        ;

        $this->quoteMock = self::createMock(Quote::class);
        $this->quoteMock->method('getAllVisibleItems')
            ->willReturn([$this->allVisibleQuoteItemMock])
        ;
        $this->quoteMock->method('getAllItems')
            ->willReturn([$allItemMock])
        ;

        $this->taxBuilder = new TaxBuilder();
    }

    /**
     * @return void
     */
    public function testExecuteWithNoTaxData(): void
    {
        $this->allVisibleQuoteItemMock->expects(self::once())
            ->method('getProductType')
            ->willReturn('notBundle')
        ;

       self::assertSame([], $this->taxBuilder->execute($this->quoteMock));
    }

    /**
     * @return void
     */
    public function testExecuteBundleProductWithTaxData(): void
    {
        $this->allVisibleQuoteItemMock->expects(self::once())
            ->method('getProductType')
            ->willReturn(BundleType::TYPE_CODE)
        ;

        $expectedTaxdata[] = [
            'type_code' => 'vat',
            'subtype_code' => 'standard',
            'rate' => 20,
            'amount' => 10.00
        ];

        self::assertSame($expectedTaxdata, $this->taxBuilder->execute($this->quoteMock));
    }
}
