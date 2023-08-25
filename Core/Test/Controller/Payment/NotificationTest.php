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

namespace Magento\PhpStan\UpStreamPay\Core\Test\Controller\Payment;

use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use UpStreamPay\Core\Controller\Payment\Notification;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\NotificationService;
use Magento\Framework\Event\ManagerInterface as EventManager;
use PHPUnit\Framework\TestCase;

/**
 * Class NotificationTest
 *
 * @package Magento\PhpStan\UpStreamPay\Core\Test\Controller\Payment
 */
class NotificationTest extends TestCase
{
    private RequestInterface&MockObject $requestMock;
    private Config&MockObject $configMock;
    private NotificationService&MockObject $notificationServiceMock;
    private JsonFactory&MockObject $jsonFactoryMock;
    private Json&MockObject $jsonMock;
    private EventManager&MockObject $eventManagerMock;
    private Notification $notification;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->requestMock = self::createMock(Http::class);
        $this->configMock = self::createMock(Config::class);
        $this->notificationServiceMock = self::createMock(NotificationService::class);
        $this->jsonFactoryMock = self::createMock(JsonFactory::class);
        $this->eventManagerMock = self::createMock(EventManager::class);
        $this->jsonMock = self::createMock(Json::class);

        $this->notification = new Notification(
            $this->requestMock,
            $this->configMock,
            $this->notificationServiceMock,
            $this->jsonFactoryMock,
            $this->eventManagerMock
        );
    }

    /**
     * @return void
     */
    public function testValidateForCsrf(): void
    {
        $this->configMock->expects(self::once())
            ->method('getRsaKey')
            ->willReturn(file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'public_key.pem'))
        ;

        $signature = 'c4inmsofvt2+I2puuEqxefD1t4kmO8gKtZwTwusDcn2PXg0V5HzELGCp4xFhWM7GhpFYmj4zY5FlFPc3V/6yllE4pKwkHHeCyguHsE5i0/TGBsCRW/U8nl5qBUrgtLZ2I/tYAmmGZg4U9BeC7E0YMwb7fqNOLPWbCXaeBFYKwhKtbMpfzxhzdElyI6GWycMuzGjbvAmQeY6yIxC1p9WoJkn9LWNu5Jx7KAMFkFiBxVeMsdHjOcH378PdmjP24ERg0xHdXpJgFKjMeTj4zGZqUZIqDa+b0OJk7ETc0UvNXWIzXYfx7Cz9w2h8d0SF+9CTLgFrwmT/PY4mEQMkkHEruA==';

        $this->requestMock->expects(self::once())
            ->method('getHeader')
            ->with('X-Signature')
            ->willReturn($signature)
        ;

        $jsonContent = '{"id":"a4bbaa57-87a1-4f77-9ced-633a7e12af41","partner":"aci","method":"creditcard","status":{"action":"AUTHORIZE","state":"SUCCESS","code":"SUCCEEDED"},"date":"2021-09-24T09:48:45.631121559","plugin_result":{"status":"000.100.110","amount":175}}';
        $this->requestMock->expects(self::once())
            ->method('getContent')
            ->willReturn($jsonContent)
        ;

        $result = $this->notification->validateForCsrf($this->requestMock);

        self::assertTrue($result);
    }

    /**
     * @return void
     * @throws NotFoundException
     */
    public function testExecute(): void
    {
        $jsonContent = json_encode(['fakeNotification' => 'fakeData']);
        $notification = json_decode($jsonContent, true);

        $this->requestMock->expects(self::once())
            ->method('getContent')
            ->willReturn($jsonContent)
        ;

        $this->notificationServiceMock->expects(self::once())
            ->method('execute')
            ->with($notification)
        ;

        $this->jsonFactoryMock->expects(self::once())
            ->method('create')
            ->willReturn($this->jsonMock)
        ;

        self::assertInstanceOf($this->jsonMock::class, $this->notification->execute());
    }
}
