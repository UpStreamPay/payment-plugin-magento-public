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

namespace UpStreamPay\Core\Controller\Adminhtml\Subscriptions;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\App\Action\HttpGetActionInterface;

/**
 * Class Index
 *
 * @package UpStreamPay\Core\Controller\Adminhtml\Subscriptions
 */
class Index extends Action implements HttpGetActionInterface
{
    /**
     * Constructor
     *
     * @param Context $context
     * @param PageFactory $pageFactory
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory
    ) {
        parent::__construct($context);
    }

    /**
     * Add the main Admin Grid page
     *
     * @return Page
     */
    public function execute()
    {
        $resultPage = $this->pageFactory->create();
        $resultPage->setActiveMenu('UpStreamPay_Core::subscriptions');
        $resultPage->getConfig()->getTitle()->prepend(__('Subscriptions grid'));

        return $resultPage;
    }
}
