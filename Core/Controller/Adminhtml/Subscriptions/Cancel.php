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
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Model\Subscription\CancelService;
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
    const URL_PATH_CANCEL = 'upstreampay/subscriptions/cancel';

    /**
     * @param Context $context
     * @param SubscriptionRepository $subscriptionRepository
     * @param CancelService $cancelService
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context                                 $context,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly CancelService $cancelService,
        private readonly LoggerInterface $logger
    )
    {
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $entityId = $this->getRequest()->getParam('id', false);

        if ($entityId) {
            try {
                $subscription = $this->subscriptionRepository->getById((int)$entityId);

                if ($subscription->canCancel()) {
                    $this->cancelService->execute($subscription->getEntityId());
                    $this->messageManager->addSuccessMessage(__('Subscription canceled'));

                }
            } catch (\Throwable $exception) {
                $this->messageManager->addErrorMessage(__('Subscriptions cancel failed'));
                $errorMessage = sprintf(
                    'Error while trying to cancel subscription with ID %s from admin because %s',
                    $entityId,
                    $exception->getMessage()
                );
                $this->logger->error($errorMessage);
                $this->logger->error($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);
            }
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
