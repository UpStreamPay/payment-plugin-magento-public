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

namespace UpStreamPay\Core\Controller\Wallet;

use Magento\Customer\Model\Session;
use Magento\Customer\Model\Url;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\SessionException;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Config\Source\Debug;

/**
 * Class Index
 *
 * @package UpStreamPay\Core\Controller\Wallet
 */
class Index implements HttpGetActionInterface
{
    /**
     * @param Session $session
     * @param PageFactory $pageFactory
     * @param Url $customerUrl
     * @param RedirectFactory $redirectFactory
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Session $session,
        private readonly PageFactory $pageFactory,
        private readonly Url $customerUrl,
        private readonly RedirectFactory $redirectFactory,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Display the wallet widget in customer account if the wallet is enabled & customer logged in.
     *
     * @throws SessionException
     */
    public function execute(): ResultInterface
    {
        $logDebugMode = $this->config->getDebugMode() === Debug::DEBUG_VALUE;

        if ($logDebugMode) {
            $this->logger->debug('Request made to display wallet widget in customer account.');
        }

        if (!$this->config->getWalletEnabled()) {
            if ($logDebugMode) {
                $this->logger->debug('But the wallet widget is disabled. Redirecting to customer dashboard');
            }

            $resultRedirect = $this->redirectFactory->create();
            $resultRedirect->setPath($this->customerUrl->getDashboardUrl());
        } elseif ($this->session->authenticate()) {
            $resultPage = $this->pageFactory->create();
            $resultPage->getConfig()->getTitle()->set(__('Stored Payment Methods'));

            if ($logDebugMode) {
                $this->logger->debug('Wallet widget enabled & customer logged in, loading page.');
            }

            return $resultPage;
        } else {
            $resultRedirect = $this->redirectFactory->create();
            $resultRedirect->setPath($this->customerUrl->getLoginUrl());

            if ($logDebugMode) {
                $this->logger->debug('But the customer is not logged in. Redirectin to login page.');
            }
        }

        return $resultRedirect;
    }
}
