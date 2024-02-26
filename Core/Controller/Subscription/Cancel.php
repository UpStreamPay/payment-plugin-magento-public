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

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Model\Subscription\CancelService;
use UpStreamPay\Core\Model\SubscriptionRepository;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
/**
 * Class Cancel
 *
 * @package UpStreamPay\Core\Controller\Subscriptions
 */
class Cancel implements HttpPostActionInterface
{
    /**
     * Url path
     */
    public const URL_PATH = 'upstreampay/subscription/cancel';

    /**
     * @param SubscriptionRepository $subscriptionRepository
     * @param CancelService $cancelService
     * @param RequestInterface $request
     * @param LoggerInterface $logger
     * @param RedirectFactory $redirectFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly CancelService $cancelService,
        private readonly RequestInterface $request,
        private readonly LoggerInterface $logger,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager
    )
    {}

    /**
     * @inheritdoc
     * @return Redirect
     */
    public function execute(): Redirect
    {
        $subscriptionId = $this->request->getParam('subscription_id', false);

        if ($subscriptionId) {
            try {
                $subscription = $this->subscriptionRepository->getById((int)$subscriptionId);
                if ($subscription->canCancel()) {
                    $this->cancelService->execute($subscription->getEntityId());
                    $this->messageManager->addSuccessMessage(__('Subscription canceled'));
                }
            } catch (\Throwable $exception) {
                $errorMessage = sprintf(
                    'Error while trying to cancel subscription with ID %s',
                    $subscriptionId
                );
                $this->logger->error($errorMessage);
                $this->logger->error($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);
            }
        }

        return $this->redirectFactory->create()->setPath('*/*/index');
    }
}
