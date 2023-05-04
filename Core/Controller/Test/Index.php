<?php

namespace UpStreamPay\Core\Controller\Test;

use Magento\Framework\App\ActionInterface;

class Index implements ActionInterface
{
    public function execute()
    {
        die('Hello World!');
    }
}