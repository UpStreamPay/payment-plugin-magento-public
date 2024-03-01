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

namespace UpStreamPay\Core\Controller\Subscription;

use Magento\Customer\Model\Session;
use Magento\Customer\Model\Url;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\SessionException;
use Magento\Framework\View\Result\PageFactory;
use UpStreamPay\Core\Model\Config;

/**
 * Class Index
 *
 * @package UpStreamPay\Core\Controller\Subscription
 */
class Index implements HttpGetActionInterface
{
    /**
     * @param Session $session
     * @param PageFactory $pageFactory
     * @param Url $customerUrl
     * @param RedirectFactory $redirectFactory
     * @param Config $config
     */
    public function __construct(
        private readonly Session $session,
        private readonly PageFactory $pageFactory,
        private readonly Url $customerUrl,
        private readonly RedirectFactory $redirectFactory,
        private readonly Config $config
    ) {
    }

    /**
     * Display the subscription list in customer account.
     *
     * @return ResultInterface
     * @throws SessionException
     */
    public function execute(): ResultInterface
    {
        $resultRedirect = $this->redirectFactory->create();

        if ($this->config->getSubscriptionPaymentEnabled() && $this->config->getSubscriptionPaymentEnableCustomerInterface()) {
            if ($this->session->authenticate()) {
                $resultPage = $this->pageFactory->create();
                $resultPage->getConfig()->getTitle()->set(__('Subscriptions'));

                return $resultPage;
            } else {
                $resultRedirect->setPath($this->customerUrl->getLoginUrl());
            }
        }
        
        return $resultRedirect;
    }
}
