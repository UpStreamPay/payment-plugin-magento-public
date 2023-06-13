<?php
/**
 * UpStream Pay
 *
 * Copyright (c) 2019-2023 UpStream Pay.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Claranet France <info@fr.clara.net>
 */
declare(strict_types=1);

namespace UpStreamPay\Core\Model;

use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Class UpStreamPayConfigProvider
 *
 * @package UpStreamPay\Core\Model
 */
class UpStreamPayConfigProvider implements ConfigProviderInterface
{
    /**
     * @param Config $config
     * @param EncryptorInterface $encryptor
     */
    public function __construct(
        private readonly Config $config,
        private readonly EncryptorInterface $encryptor
    ) {
    }

    /**
     * Return the payment method config so that we can use it in checkout.
     *
     * @return array[]
     */
    public function getConfig(): array
    {
        return [
            'payment' => [
                'UpStreamPay' => [
                    'entityId' => $this->config->getEntityId(),
                    'mode' => $this->config->getMode(),
                    'apiKey' => $this->encryptor->decrypt($this->config->getApiKey())
                ]
            ]
        ];
    }
}
