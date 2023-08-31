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

use Magento\Checkout\Model\ConfigProviderInterface;

/**
 * Class UpStreamPayConfigProvider
 *
 * @codeCoverageIgnore
 *
 * @package UpStreamPay\Core\Model
 */
class UpStreamPayConfigProvider implements ConfigProviderInterface
{
    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config,
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
                    'apiKey' => $this->config->getApiKey(),
                    'errorMessage' => $this->config->getErrorMessage(),
                    'paymentMethodCode' => Config::METHOD_CODE_UPSTREAM_PAY
                ]
            ]
        ];
    }
}
