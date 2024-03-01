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

namespace UpStreamPay\Core\Block\Customer\Account;

use Magento\Customer\Block\Account\SortLinkInterface;
use Magento\Framework\App\DefaultPathInterface;
use Magento\Framework\View\Element\Html\Link\Current;
use Magento\Framework\View\Element\Template\Context;
use UpStreamPay\Core\Controller\Subscription\Cancel;
use UpStreamPay\Core\Model\Config;

/**
 * Class Subscription
 *
 * @package UpStreamPay\Core\Block\Customer\Account
 */
class Subscription extends Current implements SortLinkInterface
{
    /**
     * @param Config $config
     * @param Context $context
     * @param DefaultPathInterface $defaultPath
     * @param array $data
     */
    public function __construct(
        private readonly Config $config,
        Context $context,
        DefaultPathInterface $defaultPath,
        array $data = []
    ) {
        parent::__construct($context, $defaultPath, $data);
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml(): string
    {
        if ($this->config->getSubscriptionPaymentEnabled()
            && $this->config->getSubscriptionPaymentEnableCustomerInterface()) {
            return parent::_toHtml();
        }

        return '';
    }

    /**
     * @inheritdoc
     */
    public function getSortOrder(): string
    {
        return $this->getData(self::SORT_ORDER);
    }

    /**
     * @return string
     */
    public function getCancelUrl(): string
    {
        return $this->getUrl(Cancel::URL_PATH);
    }
}
