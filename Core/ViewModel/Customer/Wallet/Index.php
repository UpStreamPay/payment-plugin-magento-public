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

namespace UpStreamPay\Core\ViewModel\Customer\Wallet;

use Magento\Customer\Model\Session;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use UpStreamPay\Client\Model\Client\ClientInterface;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\Config\Source\Debug;

/**
 * Class Index
 *
 */
class Index implements ArgumentInterface
{
    /**
     * @param ClientInterface $client
     * @param Session $customerSession
     * @param Json $jsonSerializer
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly Session $customerSession,
        private readonly Json $jsonSerializer,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Get the wallet session.
     *
     * @return string
     */
    public function getWalletSession(): string
    {
        $customerId = (int)$this->customerSession->getId();
        $logDebugMode = $this->config->getDebugMode() === Debug::DEBUG_VALUE;

        if ($logDebugMode) {
            $this->logger->debug("Request made to create wallet session for customer $customerId.");
        }

        try {
            return $this->jsonSerializer->serialize(
                $this->client->createWalletSession(
                    $customerId
                )
            );
        } catch (Throwable $exception) {
            $this->logger->error(
                "Error while trying to display wallet widget in customer account for customer $customerId"
            );
            $this->logger->error($exception->getMessage(), ['exception' => $exception->getTraceAsString()]);

            return '';
        }
    }

    /**
     * Get the config needed to init the wallet widget in frontend.
     *
     * @return array
     */
    public function getConfigValues(): array
    {
        return [
            'mode' => $this->config->getMode(),
            'entityId' => $this->config->getEntityId(),
            'apiKey' => $this->config->getApiKey(),
            //The JS part of the string is not needed because the KO script requires the path without the .js.
            'widgetUrl' => str_replace('.js', '', $this->config->getWidgetUrl()),
            'errorMessage' => $this->config->getErrorMessage()
        ];
    }
}
