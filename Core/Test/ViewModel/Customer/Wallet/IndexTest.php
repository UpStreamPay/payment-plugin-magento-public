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

namespace UpStreamPay\Core\ViewModel\Customer\Wallet;

use Exception;
use Magento\Customer\Model\Session;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Config\Source\Debug;
use UpStreamPay\Core\Model\Config\Source\Mode;

/**
 * Class IndexTest
 *
 * @package UpStreamPay\Core\ViewModel\Customer\Wallet
 */
class IndexTest extends TestCase
{
    private ClientInterface&MockObject $clientMock;
    private Index $indexViewModel;

    protected function setUp(): void
    {
        $this->clientMock = self::createMock(ClientInterface::class);
        $customerSessionMock = self::createMock(Session::class);
        $jsonSerializerMock = self::createMock(Json::class);
        $configMock = self::createMock(Config::class);
        $loggerMock = self::createMock(LoggerInterface::class);

        $jsonSerializerMock->method('serialize')
            ->willReturn('{"session_id":"rere-3434","owner_reference":"2","merchant":"pictime"}');

        $configMock->method('getDebugMode')
            ->willReturn(Debug::DEBUG_VALUE)
        ;
        $configMock->method('getMode')
            ->willReturn(Mode::SANDBOX_VALUE)
        ;
        $configMock->method('getEntityId')
            ->willReturn('fakeEntityId')
        ;
        $configMock->method('getApiKey')
            ->willReturn('fakeApiKey')
        ;
        $configMock->method('getWidgetUrl')
            ->willReturn('http://foo/bar.js')
        ;
        $configMock->method('getErrorMessage')
            ->willReturn('Fake error message')
        ;

        $this->indexViewModel = new Index(
            $this->clientMock,
            $customerSessionMock,
            $jsonSerializerMock,
            $configMock,
            $loggerMock
        );
    }

    /**
     * @return void
     */
    public function testGetWalletSessionWithException(): void
    {
        $this->clientMock->expects(self::once())
            ->method('createWalletSession')
            ->willThrowException(new Exception())
        ;

        self::assertEquals('', $this->indexViewModel->getWalletSession());
    }

    /**
     * @return void
     */
    public function testGetWalletSessionWithoutException(): void
    {
        $this->clientMock->expects(self::once())
            ->method('createWalletSession')
            ->willReturn(
                [
                    'session_id' => 'rere-3434',
                    'owner_reference' => '2',
                    'merchant' => 'pictime',
                ]
            )
        ;

        self::assertEquals(
            '{"session_id":"rere-3434","owner_reference":"2","merchant":"pictime"}',
            $this->indexViewModel->getWalletSession()
        );
    }

    /**
     * @return void
     */
    public function testGetConfigValues(): void
    {
        $actual = [
            'mode' => Mode::SANDBOX_VALUE,
            'entityId' => 'fakeEntityId',
            'apiKey' => 'fakeApiKey',
            'widgetUrl' => str_replace('.js', '', 'http://foo/bar.js'),
            'errorMessage' => 'Fake error message'
        ];

        self::assertEquals($actual, $this->indexViewModel->getConfigValues());
    }
}
