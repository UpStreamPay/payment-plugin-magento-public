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
 * Class Debug
 *
 * @codeCoverageIgnore
 *
 * @package UpStreamPay\Core\Model\Config\Source
 */
class Debug implements OptionSourceInterface
{
    public const DISABLED_VALUE = 'disabled';
    public const SIMPLE_VALUE = 'simple';
    public const DEBUG_VALUE = 'debug';

    /**
     * Return debug modes
     *
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::DISABLED_VALUE,
                'label' => __('Disabled')
            ],
            [
                'value' => self::SIMPLE_VALUE,
                'label' => __('Simple')
            ],
            [
                'value' => self::DEBUG_VALUE,
                'label' => __('Debug')
            ]
        ];
    }
}
