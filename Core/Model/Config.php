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

namespace UpStreamPay\Core\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use UpStreamPay\Core\Model\Config\Source\Mode;

/**
 * Class Config
 *
 * @package Model
 */
class Config
{
    public const METHOD_CODE_UPSTREAM_PAY = 'upstream_pay';

    public const ACTIVE_CONFIG_PATH = 'payment/upstream_pay/active';
    public const DEBUG_MODE_CONFIG_PATH = 'payment/upstream_pay/debug';
    public const MODE_CONFIG_PATH = 'payment/upstream_pay/mode';
    public const TITLE_CONFIG_PATH = 'payment/upstream_pay/title';
    public const ERROR_MESSAGE_CONFIG_PATH = 'payment/upstream_pay/error_message';
    public const ORDER_STATUS_CONFIG_PATH = 'payment/upstream_pay/order_status';
    public const CLIENT_ID_CONFIG_PATH = 'payment/upstream_pay/api_config/client_id';
    public const ENTITY_ID_CONFIG_PATH = 'payment/upstream_pay/api_config/entity_id';
    public const CLIENT_SECRET_CONFIG_PATH = 'payment/upstream_pay/api_config/client_secret';
    public const API_KEY_CONFIG_PATH = 'payment/upstream_pay/api_config/api_key';
    public const RSA_SANDBOX_KEY_CONFIG_PATH = 'payment/upstream_pay/api_config/rsa_sandbox_key';
    public const RSA_PRODUCTION_KEY_CONFIG_PATH = 'payment/upstream_pay/api_config/rsa_production_key';
    public const PAYMENT_ACTION_CONFIG_PATH = 'payment/upstream_pay/payment_action';

    /**
     * @param ScopeConfigInterface $config
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly ScopeConfigInterface $config,
        private readonly EncryptorInterface $encryptor
    ) {}

    /**
     * Return true if payment method is active, false otherwise.
     *
     * @return bool
     */
    public function getIsActive(): bool
    {
        return (bool) $this->config->getValue(self::ACTIVE_CONFIG_PATH, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Return true if debug mode is enabled, false otherwise.
     *
     * @return bool
     */
    public function getIsDebugEnabled(): bool
    {
        return (bool) $this->config->getValue(self::DEBUG_MODE_CONFIG_PATH, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Return mode configured (sandbox or production).
     *
     * @return string
     */
    public function getMode(): string
    {
        return $this->config->getValue(self::MODE_CONFIG_PATH, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Return the title for the payment method.
     *
     * @return string
     */
    public function getTitle(): string
    {
        return $this->config->getValue(self::TITLE_CONFIG_PATH, ScopeInterface::SCOPE_STORE);
    }

    public function getErrorMessage(): string
    {
        return $this->config->getValue(self::ERROR_MESSAGE_CONFIG_PATH, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Return the default order status.
     *
     * @return string
     */
    public function getDefaultOrderStatus(): string
    {
        return $this->config->getValue(self::ORDER_STATUS_CONFIG_PATH, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get the client ID (API).
     *
     * @return ?string
     */
    public function getClientId(): ?string
    {
        return $this->config->getValue(self::CLIENT_ID_CONFIG_PATH, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get the entity ID (API).
     *
     * @return ?string
     */
    public function getEntityId(): ?string
    {
        return $this->config->getValue(self::ENTITY_ID_CONFIG_PATH, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get client secret value (API).
     * It's a config with an obscure type.
     *
     * @return ?string
     */
    public function getClientSecret(): ?string
    {
        return $this->encryptor->decrypt(
            trim($this->config->getValue(self::CLIENT_SECRET_CONFIG_PATH, ScopeInterface::SCOPE_STORE))
        );
    }

    /**
     * Get the API key (API).
     * It's a config with an obscure type.
     *
     * @return ?string
     */
    public function getApiKey(): ?string
    {
        return $this->encryptor->decrypt(
            trim($this->config->getValue(self::API_KEY_CONFIG_PATH, ScopeInterface::SCOPE_STORE))
        );
    }

    /**
     * Get the payment action (authorize or authorize_capture).
     *
     * @return string
     */
    public function getPaymentAction(): string
    {
        return $this->config->getValue(self::PAYMENT_ACTION_CONFIG_PATH, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get the proper RSA key based on current mode.
     *
     * @return string
     */
    public function getRsaKey(): string
    {
        if ($this->getMode() === Mode::SANDBOX_VALUE) {
            return $this->config->getValue(self::RSA_SANDBOX_KEY_CONFIG_PATH);
        } else {
            return $this->config->getValue(self::RSA_PRODUCTION_KEY_CONFIG_PATH);
        }
    }
}
