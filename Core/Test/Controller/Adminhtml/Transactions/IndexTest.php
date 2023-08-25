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

namespace UpStreamPay\Core\Test\Controller\Adminhtml\Transactions;

use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Page\Config;
use Magento\Framework\View\Result\PageFactory;
use Magento\Backend\Model\View\Result\Page;
use Magento\Backend\App\Action\Context;
use PHPUnit\Framework\MockObject\MockObject;
use UpStreamPay\Core\Controller\Adminhtml\Transactions\Index;
use PHPUnit\Framework\TestCase;

/**
 * Class IndexTest
 *
 * @package UpStreamPay\Core\Controller\Adminhtml\Transactions
 */
class IndexTest extends TestCase
{
    private PageFactory&MockObject $pageFactoryMock;
    private Index $controller;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $contextMock = self::createMock(Context::class);
        $this->pageFactoryMock = self::createMock(PageFactory::class);

        $this->controller = new Index(
            $contextMock,
            $this->pageFactoryMock
        );
    }

    /**
     * @return void
     */
    public function testExecute(): void
    {
        $pageMock = self::createMock(Page::class);
        $pageConfigMock = self::createMock(Config::class);
        $pageTitleMock = self::createMock(Title::class);

        $pageMock->expects(self::once())
            ->method('setActiveMenu')
            ->with('UpStreamPay_Core::transactions');

        $pageTitleMock->expects(self::once())
            ->method('prepend')
            ->with(__('Transactions grid'))
        ;

        $pageConfigMock->expects(self::once())
            ->method('getTitle')
            ->willReturn($pageTitleMock)
        ;

        $pageMock->expects(self::once())
            ->method('getConfig')
            ->willReturn($pageConfigMock)
        ;

        $this->pageFactoryMock->expects(self::once())
            ->method('create')
            ->willReturn($pageMock)
        ;

        $result = $this->controller->execute();

        self::assertInstanceOf(Page::class, $result);
    }
}
