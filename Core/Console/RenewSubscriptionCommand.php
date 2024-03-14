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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Subscription\RenewSubscriptionService;
use UpStreamPay\Core\Model\SubscriptionRepository;

/**
 * Class RenewSubscriptionCommand
 *
 * @package UpStreamPay\Core\Console
 */
class RenewSubscriptionCommand extends Command
{
    /**
     * @param SubscriptionRepository $subscriptionRepository
     * @param RenewSubscriptionService $renewSubscriptionService
     * @param State $state
     * @param Config $config
     * @param LoggerInterface $logger
     * @param null $name
     */
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly RenewSubscriptionService $renewSubscriptionService,
        private readonly State $state,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
        $name = null
    )
    {
        parent::__construct($name);
    }

    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('upstreampay:subscription:renew');
        $this->setDescription('Renew subscriptions');

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->state->setAreaCode(Area::AREA_GLOBAL);

        if ($this->config->getDebugMode()) {
            $this->logger->debug('Command renewal process start.');
        }

        if ($this->config->getSubscriptionPaymentEnabled()) {
            //TODO Add a progressbar
            try {
                $subscriptionToRenew = $this->subscriptionRepository->getAllSubscriptionsToRenew();
                if (!empty($subscriptionToRenew)) {
                    foreach ($subscriptionToRenew as $subscription) {
                        if ($this->config->getDebugMode()) {
                            $this->logger->debug(
                                sprintf(
                                    'Attempting to renew subscription with ID %s',
                                    $subscription->getEntityId()
                                )
                            );
                        }

                        $this->renewSubscriptionService->execute($subscription);
                    }
                } else {
                    if ($this->config->getDebugMode()) {
                        $this->logger->debug('No subscription to renew found for the day.');
                    }

                    $output->writeln(
                        "<info>There are no subscriptions to renew for today's date.</info>",
                        OutputInterface::OUTPUT_NORMAL
                    );
                }
            } catch (\Exception $exception) {
                $this->logger->error('Error while trying to run cron to renew the subscription of todays date.');
                $this->logger->error($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);

                $msg = $exception->getMessage();
                $output->writeln("<error>$msg</error>", OutputInterface::OUTPUT_NORMAL);

                return Cli::RETURN_FAILURE;
            }
        } else {
            $this->logger->error('Renew subscription cron is running but the subscription feature is disabled.');

            $output->writeln(
                "<info>The reccuring payments are disabled, enable it in admin.</info>",
                OutputInterface::OUTPUT_NORMAL
            );
        }

        if ($this->config->getDebugMode()) {
            $this->logger->debug('Command renewal process done.');
        }

        return Cli::RETURN_SUCCESS;
    }
}
