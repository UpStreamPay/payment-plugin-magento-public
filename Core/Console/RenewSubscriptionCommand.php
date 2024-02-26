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
use UpStreamPay\Core\Api\Data\SubscriptionInterface;
use UpStreamPay\Core\Model\Subscription\RenewSubscriptionService;
use UpStreamPay\Core\Model\SubscriptionRepository;

class RenewSubscriptionCommand extends Command
{
    /**
     * Initialize dependencies.
     *
     * @param null $name
     */
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly RenewSubscriptionService $renewSubscriptionService,
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
        //TODO Check that we have enabled the recurring payments in admin before doing anything.
        //TODO If disabled output a message to the user letting him know that the recurring payments are disabled.
        //TODO Add a progressbar
        try {
            $subscriptionToRenew = $this->subscriptionRepository->getAllSubscriptionsToRenew();
            if (!empty($subscriptionToRenew)) {
                /** @var SubscriptionInterface $subscription */
                foreach ($subscriptionToRenew as $subscription) {
                    /** Call renewSubService */
                    $this->renewSubscriptionService->execute($subscription);
                }
            }

            return Cli::RETURN_SUCCESS;
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            $output->writeln("<error>$msg</error>", OutputInterface::OUTPUT_NORMAL);
            return Cli::RETURN_FAILURE;
        }
    }
}
