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

namespace UpStreamPay\Core\Test\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Config\Source\Mode;

/**
 * Class ConfigTest
 *
 * @package UpStreamPay\Core\Test\Model
 */
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

    public function testGetIsActive()
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::ACTIVE_CONFIG_PATH)
            ->willReturn('1');

        self::assertEquals(true, $this->config->getIsActive());
    }

    public function testGetDebugModeDisabled()
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::DEBUG_MODE_CONFIG_PATH)
            ->willReturn('disabled');

        self::assertEquals('disabled', $this->config->getDebugMode());
    }

    public function testGetDebugModeEnabled()
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::DEBUG_MODE_CONFIG_PATH)
            ->willReturn('simple');

        self::assertEquals('simple', $this->config->getDebugMode());
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

    public function testGetClientSecretFilled()
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

    public function testGetErrorMessage()
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::ERROR_MESSAGE_CONFIG_PATH)
            ->willReturn('Error message');

        self::assertEquals('Error message', $this->config->getErrorMessage());
    }

    public function testGetDefaultOrderStatus()
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::ORDER_STATUS_CONFIG_PATH)
            ->willReturn('Pending');

        self::assertEquals('Pending', $this->config->getDefaultOrderStatus());
    }

    public function testGetClientIdNotNull()
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::CLIENT_ID_CONFIG_PATH)
            ->willReturn('fake_hash');

        $this->encryptorMock
            ->expects(self::once())
            ->method('decrypt')
            ->willReturn('fake_secret');

        self::assertEquals('fake_secret', $this->config->getClientId());
    }

    public function testGetClientIdNull()
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::CLIENT_ID_CONFIG_PATH)
            ->willReturn(null);

        $this->encryptorMock
            ->expects(self::never())
            ->method('decrypt');

        self::assertNull($this->config->getClientId());
    }

    public function testGetEntityIdNotNull()
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::ENTITY_ID_CONFIG_PATH)
            ->willReturn('pictime');

        self::assertEquals('pictime', $this->config->getEntityId());
    }

    public function testGetEntityIdNull()
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::ENTITY_ID_CONFIG_PATH)
            ->willReturn(null);

        self::assertNull($this->config->getEntityId());
    }

    public function testGetApiKeyNull()
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::API_KEY_CONFIG_PATH)
            ->willReturn(null);
        $this->encryptorMock
            ->expects(self::never())
            ->method('decrypt');

        self::assertNull($this->config->getApiKey());
    }

    public function testGetApiKeyNotNull()
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::API_KEY_CONFIG_PATH)
            ->willReturn('fake_api_key_hash');
        $this->encryptorMock
            ->expects(self::once())
            ->method('decrypt')
            ->willReturn('fake_api_key_decrypted');

        self::assertEquals('fake_api_key_decrypted', $this->config->getApiKey());
    }

    public function testGetPaymentAction()
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::PAYMENT_ACTION_CONFIG_PATH)
            ->willReturn('authorize');

        self::assertEquals('authorize', $this->config->getPaymentAction());
    }

    public function testGetRsaKeyProduction()
    {
        $this->scopeConfigMock
            ->expects(self::exactly(2))
            ->method('getValue')
            ->withConsecutive([Config::MODE_CONFIG_PATH], [Config::RSA_PRODUCTION_KEY_CONFIG_PATH])
            ->willReturnOnConsecutiveCalls(Mode::PRODUCTION_VALUE, 'fake_production_rsa_key');

        self::assertEquals('fake_production_rsa_key', $this->config->getRsaKey());
    }

    public function testGetRsaKeySandbox()
    {
        $this->scopeConfigMock
            ->expects(self::exactly(2))
            ->method('getValue')
            ->withConsecutive([Config::MODE_CONFIG_PATH], [Config::RSA_SANDBOX_KEY_CONFIG_PATH])
            ->willReturnOnConsecutiveCalls(Mode::SANDBOX_VALUE, 'fake_sandbox_rsa_key');

        self::assertEquals('fake_sandbox_rsa_key', $this->config->getRsaKey());
    }

    public function testGet3dsExemptionAttributeCodeNotNull()
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::THREEDS_EXEMPTION_ATTRIBUTE_CODE)
            ->willReturn('attribute_code');

        self::assertEquals('attribute_code', $this->config->get3dsExemptionAttributeCode());
    }

    public function testGet3dsExemptionAttributeCodeNull()
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::THREEDS_EXEMPTION_ATTRIBUTE_CODE)
            ->willReturn(null);

        self::assertNull($this->config->get3dsExemptionAttributeCode());
    }

    public function testGet3dsChallengeIndicatorAttributeCodeNotNull()
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::THREEDS_CHALLENGE_INDICATOR_ATTRIBUTE_CODE)
            ->willReturn('attribute_code');

        self::assertEquals('attribute_code', $this->config->get3dsChallengeIndicatorAttributeCode());
    }

    public function testGet3dsChallengeIndicatorAttributeCodeNull()
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::THREEDS_CHALLENGE_INDICATOR_ATTRIBUTE_CODE)
            ->willReturn(null);

        self::assertNull($this->config->get3dsChallengeIndicatorAttributeCode());
    }

    /**
     * @return void
     */
    public function testGetCustomerTinAttributeCodeNotNull(): void
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::CUSTOMER_TIN_ATTRIBUTE_CODE_CONFIG_PATH)
            ->willReturn('attribute_code');

        self::assertEquals('attribute_code', $this->config->getCustomerTinAttributeCode());
    }

    /**
     * @return void
     */
    public function testGetCustomerTinAttributeCodeNull(): void
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::CUSTOMER_TIN_ATTRIBUTE_CODE_CONFIG_PATH)
            ->willReturn(null);

        self::assertNull($this->config->getCustomerTinAttributeCode());
    }

    /**
     * @return void
     */
    public function testGetWidgetUrl(): void
    {
        $this->scopeConfigMock
            ->expects(self::once())
            ->method('getValue')
            ->with(Config::WIDGET_URL_CONFIG_PATH)
            ->willReturn('https://foo.bar');

        self::assertEquals('https://foo.bar', $this->config->getWidgetUrl());
    }
}
