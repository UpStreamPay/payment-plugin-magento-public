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

namespace UpStreamPay\Core\Console;

use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\ProgressBarFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UpStreamPay\Core\Api\SubscriptionRetryRepositoryInterface;
use UpStreamPay\Core\Model\Subscription\RetryService;

/**
 * Class RetrySubscriptionPaymentCommand
 *
 * @package UpStreamPay\Core\Console
 */
class RetrySubscriptionPaymentCommand extends Command
{
    public const RETRY_COMMAND_NAME = 'upstreampay:subscription:retry';

    /**
     * @param SubscriptionRetryRepositoryInterface $subscriptionRetryRepository
     * @param ProgressBarFactory $progressBarFactory
     * @param RetryService $retryService
     */
    public function __construct(
        private readonly SubscriptionRetryRepositoryInterface $subscriptionRetryRepository,
        private readonly ProgressBarFactory $progressBarFactory,
        private readonly RetryService $retryService
    )
    {
        parent::__construct();
    }

    /**
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName(self::RETRY_COMMAND_NAME)
            ->setDescription(
                "Will retry payment (duplicate authorize or capture) of every subscription in the retry table."
            )
        ;

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws LocalizedException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Gather all subscription to retry payment on.</info>');

        $subscriptionsToRetry = $this->subscriptionRetryRepository->getAllSubscriptionToRetryPayment();

        /** @var ProgressBar $progress */
        $progressBar = $this->progressBarFactory->create(
            [
                'output' => $output,
                'max' => count($subscriptionsToRetry),
            ]
        );

        $progressBar->setFormat(
            '%current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s%'
        );

        $progressBar->start();

        foreach ($subscriptionsToRetry as $subscriptionRetry) {
            $this->retryService->execute($subscriptionRetry);
            $progressBar->advance();
        }

        $progressBar->finish();
        $output->write(PHP_EOL);
        $output->writeln('<info>Subscription retry finished without error.</info>');

        return Cli::RETURN_SUCCESS;
    }
}
