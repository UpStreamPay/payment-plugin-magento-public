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
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use UpStreamPay\Core\Model\SubscriptionRepository;

/**
 * Class Cancel
 *
 * @package UpStreamPay\Core\Controller\Adminhtml\Subscriptions
 */
class Cancel extends Action implements HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * Url path
     */
    const URL_PATH_CANCEL = 'core/subscriptions/cancel';

    /**
     * @param Context $context
     * @param SubscriptionRepository $subscriptionRepository
     */
    public function __construct(
        Context                                 $context,
        private readonly SubscriptionRepository $subscriptionRepository
    )
    {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     * @return Redirect
     * @throws LocalizedException
     */
    public function execute(): Redirect
    {
        if ($entity_id = $this->getRequest()->getParam('id', false)) {
            $subscription = $this->subscriptionRepository->getById($entity_id);
            if ($subscription->canCancel()) {
                try {
                    /** TODO: Call cancel service */

                    $this->messageManager->addSuccessMessage(__('Subscription canceled'));
                } catch (\Exception $e) {
                    $this->messageManager->addErrorMessage(__('Subscriptions cancel failed'));
                }

            }
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
