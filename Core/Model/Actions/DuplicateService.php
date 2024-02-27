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

namespace UpStreamPay\Core\Model\Actions;

use GuzzleHttp\Exception\GuzzleException;
use JsonException;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Math\FloatComparator;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\Order\Payment\Processor;
use UpStreamPay\Client\Model\Client\Client;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Session\Order\OrderService;
use UpStreamPay\Core\Model\Session\PurseSessionDataManager;
use UpStreamPay\Core\Observer\SetOrderSentToPurseObserver;

/**
 * Class DuplicateService
 *
 * @package UpStreamPay\Core\Model\Actions
 */
class DuplicateService
{
    /**
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderTransactionsRepositoryInterface $orderTransactionsRepository
     * @param Config $config
     * @param FloatComparator $floatComparator
     * @param Client $client
     * @param OrderService $orderService
     * @param EventManager $eventManager
     * @param Processor $paymentProcessor
     * @param CartRepositoryInterface $cartRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderSender $orderSender
     */
    public function __construct(
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository,
        private readonly Config $config,
        private readonly FloatComparator $floatComparator,
        private readonly Client $client,
        private readonly OrderService $orderService,
        private readonly EventManager $eventManager,
        private readonly Processor $paymentProcessor,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderSender $orderSender,
    )
    {
    }


    /**
     * @throws GuzzleException
     * @throws JsonException
     */
    public function execute(Quote $quote, $transactionId, $renewOrder): void
    {
        $body = $this->orderService->execute($quote, true);
        try {
            $response = $this->client->duplicate($transactionId, $body);

            if ($response['status']['state'] === 'SUCCESS') {
                $this->eventManager->dispatch(SetOrderSentToPurseObserver::EVENT_NAME, [
                    'order' => $renewOrder
                ]);

                $quote
                    ->getPayment()
                    ->setData(PurseSessionDataManager::PAYMENT_PURSE_SESSION_ID, $response['session_id'])
                    ->setData(
                        PurseSessionDataManager::QUOTE_PAYMENT_PURSE_SESSION_AMOUNT_KEY,
                        $response['plugin_result']['amount']
                    );

                $this->cartRepository->save($quote);

                $renewOrder
                    ->getPayment()
                    ->setData(PurseSessionDataManager::PAYMENT_PURSE_SESSION_ID, $response['session_id']);

                $this->orderRepository->save($renewOrder);

                $payment = $renewOrder->getPayment();
                $this->paymentProcessor->order($payment, $renewOrder->getBaseTotalDue());
                $this->orderRepository->save($renewOrder);
                $this->orderSender->send($renewOrder);
            }
        } catch (\Exception $exception) {
            echo $exception->getTraceAsString();
        }
    }
}
