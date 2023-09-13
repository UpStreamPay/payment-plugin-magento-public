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

namespace UpStreamPay\Core\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Class Mode
 *
 * @codeCoverageIgnore
 *
 * @package UpStreamPay\Core\Model\Config\Source
 */
class Mode implements OptionSourceInterface
{
    public const SANDBOX_VALUE = 'sandbox';
    public const PRODUCTION_VALUE = 'production';

    /**
     * Return possible mode for admin config.
     *
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::SANDBOX_VALUE,
                'label' => __('Sandbox')
            ],
            [
                'value' => self::PRODUCTION_VALUE,
                'label' => __('Production')
            ]
        ];
    }
}
