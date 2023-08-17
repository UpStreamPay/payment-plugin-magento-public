<?php

namespace UpStreamPay\Core\Test\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Config\Source\Mode;

class ConfigTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfigMock;
    private EncryptorInterface&MockObject $encryptorMock;
    private Config $config;

    protected function setUp(): void
    {
        $this->scopeConfigMock = self::createMock(ScopeConfigInterface::class);
        $this->encryptorMock = self::createMock(EncryptorInterface::class);
        $this->config = new Config($this->scopeConfigMock, $this->encryptorMock);
    }

    public function testGetTitle()
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::TITLE_CONFIG_PATH)
            ->willReturn('fake_title');

        self::assertEquals('fake_title', $this->config->getTitle());
    }

    public function testGetModeUrlSandbox()
    {
        $this->scopeConfigMock
            ->expects(self::exactly(2))
            ->method('getValue')
            ->withConsecutive([Config::MODE_CONFIG_PATH], [Config::SANDBOX_URL_KEY_CONFIG_PATH])
            ->willReturnOnConsecutiveCalls(Mode::SANDBOX_VALUE, 'fake_url_sandbox');

        self::assertEquals('fake_url_sandbox', $this->config->getModeUrl());
    }

    public function testGetModeUrlProduction()
    {
        $this->scopeConfigMock
            ->expects(self::exactly(2))
            ->method('getValue')
            ->withConsecutive([Config::MODE_CONFIG_PATH], [Config::PRODUCTION_URL_KEY_CONFIG_PATH])
            ->willReturnOnConsecutiveCalls(Mode::PRODUCTION_VALUE, 'fake_url_prod');

        self::assertEquals('fake_url_prod', $this->config->getModeUrl());
    }

    public function testGetClientSecretEmpty()
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::CLIENT_SECRET_CONFIG_PATH)
            ->willReturn(null);
        $this->encryptorMock
            ->expects(self::never())
            ->method('decrypt');

        self::assertNull($this->config->getClientSecret());
    }

    public function testGetClientSecretEmptyFilled()
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::CLIENT_SECRET_CONFIG_PATH)
            ->willReturn('fake_hash');
        $this->encryptorMock
            ->expects(self::once())
            ->method('decrypt')
            ->willReturn('fake_secret');

        self::assertEquals('fake_secret', $this->config->getClientSecret());
    }
}
