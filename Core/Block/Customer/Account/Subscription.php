<?php

namespace UpStreamPay\Core\Block\Customer\Account;

use Magento\Framework\Math\Random;
use Magento\Framework\View\Element\Html\Link;
use Magento\Customer\Block\Account\SortLinkInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Controller\Subscription\Cancel;

class Subscription extends Link implements SortLinkInterface
{
    /**
     * @param Config $config
     * @param Context $context
     * @param array $data
     * @param SecureHtmlRenderer|null $secureRenderer
     * @param Random|null $random
     */
    public function __construct(
        private readonly Config $config,
        Context $context,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null,
        ?Random $random = null
    ) {
        parent::__construct($context, $data, $secureRenderer, $random);
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml(): string
    {
        if ($this->config->getSubscriptionPaymentEnabled() && $this->config->getSubscriptionPaymentEnableCustomerInterface()) {
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