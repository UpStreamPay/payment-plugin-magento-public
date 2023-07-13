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

use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Model\OrderTransactions;

/**
 * Class CancelService
 *
 * @package UpStreamPay\Core\Model\Actions
 */
class CancelService
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly OrderTransactionsRepositoryInterface $transactionsRepository,
        private readonly OrderPaymentRepositoryInterface $orderPaymentRepository
    ) {
    }

    public function execute(array $transactionsToCancel): void
    {
        foreach ($transactionsToCancel as $transactionToCancel) {
            /** @var OrderTransactionsInterface $transaction */
            $transaction = $transactionToCancel['transaction'];

            if ($transaction->getTransactionType() !== OrderTransactions::AUTHORIZE_ACTION) {

            }
        }
    }
}
