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

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\FloatComparator;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\MethodInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Exception\AuthorizeErrorException;
use UpStreamPay\Core\Model\OrderTransactions;
use UpStreamPay\Core\Model\Config;

/**
 * Class AuthorizeService
 *
 * @package UpStreamPay\Core\Model
 */
class AuthorizeService
{
    /**
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param OrderTransactionsRepositoryInterface $orderTransactionsRepository
     * @param Config $config
     * @param FloatComparator $floatComparator
     */
    public function __construct(
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly OrderTransactionsRepositoryInterface $orderTransactionsRepository,
        private readonly Config $config,
        private readonly FloatComparator $floatComparator
    ) {
    }

    /**
     * Check if we can authorize the given amount.
     * This class doesn't call any API, it only checks & set the payment transaction state.
     *
     * If everything is ok => setIsTransactionApproved to true.
     *
     * @param InfoInterface $payment
     * @param float $amount
     *
     * @return InfoInterface
     * @throws LocalizedException
     * @throws AuthorizeErrorException
     */
    public function execute(InfoInterface $payment, float $amount): InfoInterface
    {
        $authorizeIsSuccess = true;
        $atLeastOneAuthorizeWaiting = false;
        $upStreamPaySessionId = '';
        $amountAuthorized = 0.00;

        //Get the authorized transactions with a success status for the current order.
        $this->searchCriteriaBuilder->addFilter(
            OrderTransactionsInterface::TRANSACTION_TYPE,
            OrderTransactions::AUTHORIZE_ACTION
        )->addFilter(
            OrderTransactionsInterface::ORDER_ID,
            $payment->getOrder()->getEntityId()
        );

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $authorizeTransactions = $this->orderTransactionsRepository->getList($searchCriteria)->getItems();

        //If there are no authorize transactions & we are using the order action then don't bother going any further.
        if ($this->config->getPaymentAction() === MethodInterface::ACTION_ORDER && count($authorizeTransactions) === 0) {
            return $payment;
        }

        foreach ($authorizeTransactions as $authorizeTransaction) {
            if ($upStreamPaySessionId === '') {
                $upStreamPaySessionId = $authorizeTransaction->getSessionId();
            }

            if ($authorizeTransaction->getStatus() === OrderTransactions::ERROR_STATUS) {
                $authorizeIsSuccess = false;
            } elseif ($authorizeTransaction->getStatus() === OrderTransactions::WAITING_STATUS) {
                $atLeastOneAuthorizeWaiting = true;
            } elseif ($authorizeTransaction->getStatus() === OrderTransactions::SUCCESS_STATUS) {
                $amountAuthorized += $authorizeTransaction->getAmount();
            }

            $payment->getOrder()->addCommentToStatusHistory(sprintf(
                'Transaction %s %s for %s with amount %s in status %s',
                $authorizeTransaction->getTransactionType(),
                $authorizeTransaction->getTransactionId(),
                $authorizeTransaction->getMethod(),
                $authorizeTransaction->getAmount(),
                $authorizeTransaction->getStatus()
            ));
        }

        //Every transaction has an authorize success & the amount to authorize matches the amount authorized.
        //To avoid issue when comparing floats, use built-in magento feature (it uses an epsilon of 0.00001).
        if ($authorizeIsSuccess && $this->floatComparator->equal($amountAuthorized, $amount)) {
            //Every authorize is a success, so the payment is authorized.
            $payment
                ->setTransactionId($upStreamPaySessionId)
                ->setIsTransactionClosed(false)
                ->setIsTransactionApproved(true)
                ->setCurrencyCode($payment->getOrder()->getGlobalCurrencyCode())
                ->setIsTransactionPending(false)
            ;
        } elseif ($atLeastOneAuthorizeWaiting) {
            //At least one transaction is in waiting, tell Magento that the payment is still pending.
            $payment
                ->setTransactionId($upStreamPaySessionId)
                ->setIsTransactionClosed(false)
                ->setIsTransactionPending(true)
            ;
        } elseif (!$authorizeIsSuccess) {
            //No authorize waiting has been found & at least one authorize error found.
            throw new AuthorizeErrorException(
                'At least one Authorize transaction is in error, void all Authorize in success.'
            );
        }

        return $payment;
    }
}
