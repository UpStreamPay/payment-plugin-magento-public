<?php

namespace UpStreamPay\Core\Block\Customer\Account;

use Magento\Framework\View\Element\Html\Link\Current;
use Magento\Customer\Block\Account\SortLinkInterface;
use Magento\Framework\View\Element\Template\Context;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Controller\Subscription\Cancel;
use Magento\Framework\App\DefaultPathInterface;

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