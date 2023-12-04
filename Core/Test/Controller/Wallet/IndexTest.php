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

namespace UpStreamPay\Core\Controller\Wallet;

use Magento\Customer\Model\Session;
use Magento\Customer\Model\Url;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\SessionException;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Config\Source\Debug;

/**
 * Class IndexTest
 *
 * @package UpStreamPay\Core\Controller\Wallet
 */
class IndexTest extends TestCase
{
    private Session&MockObject $customerSessionMock;
    private Config&MockObject $configMock;
    private Redirect&MockObject $redirectMock;
    private Page&MockObject $pageMock;
    private Index $indexController;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->customerSessionMock = self::createMock(Session::class);
        $this->configMock = self::createMock(Config::class);
        $pageFactoryMock = self::createMock(PageFactory::class);
        $customerUrlMock = self::createMock(Url::class);
        $redirectFactoryMock = self::createMock(RedirectFactory::class);
        $loggerMock = self::createMock(LoggerInterface::class);
        $this->redirectMock = self::createMock(Redirect::class);
        $this->pageMock = self::createMock(Page::class);
        $pageConfigMock = self::createMock(PageConfig::class);
        $titleMock = self::createMock(Title::class);
        $phraseMock = __('Stored Payment Methods');

        $this->configMock->method('getDebugMode')
            ->willReturn(Debug::DEBUG_VALUE)
        ;

        $redirectFactoryMock->method('create')
            ->willReturn($this->redirectMock)
        ;

        $pageFactoryMock->method('create')
            ->willReturn($this->pageMock)
        ;

        $titleMock->method('set')
            ->with($phraseMock)
            ->willReturnSelf()
        ;
        $pageConfigMock->method('getTitle')
            ->willReturn($titleMock)
        ;
        $this->pageMock->method('getConfig')
            ->willReturn($pageConfigMock)
        ;

        $this->indexController = new Index(
            $this->customerSessionMock,
            $pageFactoryMock,
            $customerUrlMock,
            $redirectFactoryMock,
            $this->configMock,
            $loggerMock
        );
    }

    /**
     * @return void
     * @throws SessionException
     */
    public function testExecuteIfWalletDisabled(): void
    {
        $this->configMock->expects(self::once())
            ->method('getWalletEnabled')
            ->willReturn(false)
        ;

        $this->customerSessionMock->expects(self::never())
            ->method('authenticate')
        ;

        $this->redirectMock->expects(self::once())
            ->method('setPath')
        ;

        $this->indexController->execute();
    }

    /**
     * @return void
     * @throws SessionException
     */
    public function testExecuteIfWalletEnabledAndUserLoggedOff(): void
    {
        $this->configMock->expects(self::once())
            ->method('getWalletEnabled')
            ->willReturn(true)
        ;

        $this->customerSessionMock->expects(self::once())
            ->method('authenticate')
            ->willReturn(false)
        ;

        $this->redirectMock->expects(self::once())
            ->method('setPath')
        ;

        $this->indexController->execute();
    }

    /**
     * @return void
     * @throws SessionException
     */
    public function testExecuteIfWalletEnabledAndUserLoggedIn(): void
    {
        $this->configMock->expects(self::once())
            ->method('getWalletEnabled')
            ->willReturn(true)
        ;

        $this->customerSessionMock->expects(self::once())
            ->method('authenticate')
            ->willReturn(true)
        ;

        $this->redirectMock->expects(self::never())
            ->method('setPath')
        ;

        $this->pageMock->expects(self::once())
            ->method('getConfig')
        ;

        $this->indexController->execute();
    }
}
