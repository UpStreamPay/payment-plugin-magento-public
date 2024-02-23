<?php

namespace UpStreamPay\Core\Block\Customer\Account;

use Magento\Framework\Math\Random;
use Magento\Framework\View\Element\Html\Link;
use Magento\Customer\Block\Account\SortLinkInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use UpStreamPay\Core\Model\Config;

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

    protected function _toHtml()
    {
        if ($this->config->getSubscriptionPaymentEnabled() && $this->config->getSubscriptionPaymentEnableCustomerInterface()) {
            return parent::_toHtml();
        }
        return '';
    }

    public function getSortOrder()
    {
        return $this->getData(self::SORT_ORDER);
    }

}