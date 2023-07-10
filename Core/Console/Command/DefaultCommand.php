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
namespace UpStreamPay\Core\Console\Command;

use Magento\Quote\Model\QuoteRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Client\Model\Token\TokenService;
use UpStreamPay\Core\Model\Session\Order\OrderService;

/**
 * Class DefaultCommand
 *
 * @package UpStreamPay\Core\Console\Command
 */
class DefaultCommand extends Command
{
    protected static $defaultName = 'upstreampay:default';

    public function __construct(
        private readonly ClientInterface $client,
        private readonly TokenService $tokenService,
        private readonly OrderService $orderService,
        private readonly QuoteRepository $quoteRepository
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('upstreampay:default');
        $this->setDescription('Default command for UpStreamPay');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $order = $this->orderService->execute($this->quoteRepository->get(14));

        var_dump($order);
    }
}
