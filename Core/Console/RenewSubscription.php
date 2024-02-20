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
namespace UpStreamPay\Core\Console;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UpStreamPay\Core\Model\Subscription\SubscriptionToRenewFinder;

class RenewSubscription extends Command
{
    /**
     * Initialize dependencies.
     *
     * @param null $name
     */
    public function __construct(
        private readonly SubscriptionToRenewFinder $subscriptionFinder,
        $name = null
    )
    {
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('upstreampay:subscriction:renew');
        $this->setDescription('Renew subscriptions');

        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        try {
            // EntryPoint
            $this->subscriptionFinder->execute();

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $output->writeln("<error>$msg</error>", OutputInterface::OUTPUT_NORMAL);
            return Cli::RETURN_FAILURE;
        }
    }
}
