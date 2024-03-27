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

use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\ProgressBarFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UpStreamPay\Core\Api\SubscriptionRetryRepositoryInterface;
use UpstreamPay\Core\Model\Config;
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
     * @param State $state
     * @param LoggerInterface $logger
     * @param Config $config
     */
    public function __construct(
        private readonly SubscriptionRetryRepositoryInterface $subscriptionRetryRepository,
        private readonly ProgressBarFactory $progressBarFactory,
        private readonly RetryService $retryService,
        private readonly State $state,
        private readonly LoggerInterface $logger,
        private readonly Config $config
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
        $this->state->setAreaCode(Area::AREA_GLOBAL);

        if ($this->config->getDebugMode()) {
            $this->logger->debug('Command retry subscription payment process start.');
        }

        if ($this->config->getSubscriptionPaymentEnabled()) {
            $output->writeln('<info>Gather all subscription to retry payment on.</info>');

            try {
                $subscriptionsToRetry = $this->subscriptionRetryRepository->getAllSubscriptionToRetryPayment();

                if (!empty($subscriptionsToRetry)) {
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
                        if ($this->config->getDebugMode()) {
                            $this->logger->debug(
                                sprintf(
                                    'Attempting to retry subscription payment with ID %s',
                                    $subscriptionRetry->getSubscriptionId()
                                )
                            );
                        }

                        $this->retryService->execute($subscriptionRetry);
                        $progressBar->advance();
                    }

                    $progressBar->finish();
                    $output->write(PHP_EOL);
                    $output->writeln('<info>Subscription retry finished.</info>');
                } else {
                    if ($this->config->getDebugMode()) {
                        $this->logger->debug('No subscription to retry payment on found.');
                    }

                    $output->writeln(
                        "<info>There are no subscriptions to retry payment on.</info>",
                        OutputInterface::OUTPUT_NORMAL
                    );
                }
            } catch (\Throwable $exception) {
                $this->logger->error('Error while trying to run command to retry subscription payment.');
                $this->logger->error($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);

                $msg = $exception->getMessage();
                $output->writeln("<error>$msg</error>", OutputInterface::OUTPUT_NORMAL);

                return Cli::RETURN_FAILURE;
            }
        } else {
            $this->logger->error('Retry subscription command is running but the subscription feature is disabled.');

            $output->writeln(
                "<info>The reccuring payments are disabled, enable it in admin.</info>",
                OutputInterface::OUTPUT_NORMAL
            );
        }

        if ($this->config->getDebugMode()) {
            $this->logger->debug('Command retry subscription payment process done.');
        }

        return Cli::RETURN_SUCCESS;
    }
}
