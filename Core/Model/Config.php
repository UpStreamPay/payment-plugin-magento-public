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
    public const PRODUCTION_URL_KEY_CONFIG_PATH = 'payment/upstream_pay/api_config/production_url';
    public const SANDBOX_URL_KEY_CONFIG_PATH = 'payment/upstream_pay/api_config/sandbox_url';
    public const PAYMENT_ACTION_CONFIG_PATH = 'payment/upstream_pay/payment_action';
    public const THREEDS_EXEMPTION_ATTRIBUTE_CODE = 'payment/upstream_pay/3ds_settings/3ds_exemption_attribute_code';
    public const THREEDS_CHALLENGE_INDICATOR_ATTRIBUTE_CODE = 'payment/upstream_pay/3ds_settings/challenge_indicator_attribute_code';
    public const CUSTOMER_TIN_ATTRIBUTE_CODE_CONFIG_PATH = 'payment/upstream_pay/customer_tin_attribute_code';
    public const WIDGET_URL_CONFIG_PATH = 'payment/upstream_pay/api_config/widget_url';
    public const MANAGE_STORED_PAYMENT_METHOD_CONFIG_PATH = 'payment/upstream_pay/wallet/mange_stored_payment_methods_customer_account';
    public const MERCHANT_ID_CONFIG_PATH = 'payment/upstream_pay/wallet/merchant_id';
    public const SUBSCRIPTION_PAYMENT_ENABLED = 'payment/upstream_pay/subscription_payment/enabled';
    public const SUBSCRIPTION_PAYMENT_ENABLE_CUSTOMER_INTERFACE = 'payment/upstream_pay/subscription_payment/enable_customer_interface';
    public const SUBSCRIPTION_PAYMENT_ATTR_CODE_IS_PROD_SUBSC = 'payment/upstream_pay/subscription_payment/attribute_code_is_product_subscription';
    public const SUBSCRIPTION_PAYMENT_ATTR_CODE_PROD_SUBSC_DURATION = 'payment/upstream_pay/subscription_payment/attribute_code_product_subscription_duration';
    public const SUBSCRIPTION_PAYMENT_MAX_PAYMENT_RETRY = 'payment/upstream_pay/subscription_payment/maximum_of_payment_retry';
    public const SUBSCRIPTION_PAYMENT_CRON_EXPR = 'payment/upstream_pay/subscription_payment/payment_cron_expr';
    public const SUBSCRIPTION_PAYMENT_RETRY_CRON_EXPR = 'payment/upstream_pay/subscription_payment/payment_retry_cron_expr';

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
     * Return the current debug mode
     *
     * @return string
     */
    public function getDebugMode(): string
    {
        return $this->config->getValue(self::DEBUG_MODE_CONFIG_PATH, ScopeInterface::SCOPE_STORE);
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
     * Get the url of configured mode.
     *
     * @return string
     */
    public function getModeUrl(): string
    {
        if ($this->getMode() === Mode::SANDBOX_VALUE) {
            return $this->config->getValue(self::SANDBOX_URL_KEY_CONFIG_PATH, ScopeInterface::SCOPE_STORE);
        } else {
            return $this->config->getValue(self::PRODUCTION_URL_KEY_CONFIG_PATH, ScopeInterface::SCOPE_STORE);
        }
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
     * It's a config with an obscure type.
     *
     * @return ?string
     */
    public function getClientId(): ?string
    {
        $clientIdConfigValue = $this->config->getValue(self::CLIENT_ID_CONFIG_PATH, ScopeInterface::SCOPE_STORE);

        if ($clientIdConfigValue === null) {
            return null;
        }

        return $this->encryptor->decrypt(trim($clientIdConfigValue));
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
        $config = $this->config->getValue(self::CLIENT_SECRET_CONFIG_PATH, ScopeInterface::SCOPE_STORE);

        if ($config === null) {
            return null;
        }

        return $this->encryptor->decrypt(trim($config));
    }

    /**
     * Get the API key (API).
     * It's a config with an obscure type.
     *
     * @return ?string
     */
    public function getApiKey(): ?string
    {
        $config = $this->config->getValue(self::API_KEY_CONFIG_PATH, ScopeInterface::SCOPE_STORE);

        if ($config === null) {
            return null;
        }

        return $this->encryptor->decrypt(trim($config));
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

    /**
     * Get the 3ds exemption attribute code
     * @return string|null
     */
    public function get3dsExemptionAttributeCode(): ?string
    {
        return $this->config->getValue(self::THREEDS_EXEMPTION_ATTRIBUTE_CODE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get the 3ds challenge indicator attribute code
     * @return string|null
     */
    public function get3dsChallengeIndicatorAttributeCode(): ?string
    {
        return $this->config->getValue(self::THREEDS_CHALLENGE_INDICATOR_ATTRIBUTE_CODE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get the attribute code used for TIN (tax identification number) attribute.
     *
     * @return null|string
     */
    public function getCustomerTinAttributeCode(): ?string
    {
        return $this->config->getValue(self::CUSTOMER_TIN_ATTRIBUTE_CODE_CONFIG_PATH, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get the widget URL.
     *
     * @return string
     */
    public function getWidgetUrl(): string
    {
        return $this->config->getValue(self::WIDGET_URL_CONFIG_PATH, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Return true if the customer can manage his saved payment methods from his account.
     *
     * @return bool
     */
    public function getWalletEnabled(): bool
    {
        return (bool)$this->config->getValue(
            self::MANAGE_STORED_PAYMENT_METHOD_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Return the merchant ID needed for wallet API calls.
     *
     * @return string
     */
    public function getMerchantId(): string
    {
        return $this->config->getValue(self::MERCHANT_ID_CONFIG_PATH, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Return true if the subscription payment is enabled.
     *
     * @return bool
     */
    public function getSubscriptionPaymentEnabled(): bool
    {
        return (bool)$this->config->getValue(self::SUBSCRIPTION_PAYMENT_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Return true if the subscription payment customer interface is enabled.
     *
     * @return null|int
     */
    public function getSubscriptionPaymentEnableCustomerInterface(): ?int
    {
        return (int)$this->config->getValue(self::SUBSCRIPTION_PAYMENT_ENABLE_CUSTOMER_INTERFACE, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Return the attribute code used to know if a product is subscription eligible.
     *
     * @return null|string
     */
    public function getSubscriptionPaymentProductSubscriptionAttributeCode(): ?string
    {
        return $this->config->getValue(self::SUBSCRIPTION_PAYMENT_ATTR_CODE_IS_PROD_SUBSC, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Return the attribute code used to set the subscription duration.
     *
     * @return null|string
     */
    public function getSubscriptionPaymentProductSubscriptionDurationAttributeCode(): ?string
    {
        return $this->config->getValue(self::SUBSCRIPTION_PAYMENT_ATTR_CODE_PROD_SUBSC_DURATION, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Return the maximum payment retry number
     *
     * @return null|int
     */
    public function getSubscriptionPaymentMaximumPaymentRetry(): ?int
    {
        return (int)$this->config->getValue(self::SUBSCRIPTION_PAYMENT_MAX_PAYMENT_RETRY, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Return the subscription payment cron expr.
     *
     * @return null|string
     */
    public function getSubscriptionPaymentCronExpr(): ?string
    {
        return $this->config->getValue(self::SUBSCRIPTION_PAYMENT_CRON_EXPR, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Return the subscription payment retry cron expr.
     *
     * @return null|string
     */
    public function getSubscriptionPaymentRetryCronExpr(): ?string
    {
        return $this->config->getValue(self::SUBSCRIPTION_PAYMENT_RETRY_CRON_EXPR, ScopeInterface::SCOPE_STORE);
    }
}
