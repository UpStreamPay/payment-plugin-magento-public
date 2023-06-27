<?php
/**
 * UpStream Pay
 *
 * Copyright (c) 2019-2023 UpStream Pay.
 * This file is open source and available under the MIT license.
 * See the LICENSE file for more info.
 *
 * Author: Claranet France <info@fr.clara.net>
 */
declare(strict_types=1);

namespace UpStreamPay\Core\Controller\Payment;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use phpseclib3\Crypt\PublicKeyLoader;
use Psr\Log\LoggerInterface;
use UpStreamPay\Core\Model\Config;
use UpStreamPay\Core\Model\NotificationService;

/**
 * Class Notification
 *
 * @package UpStreamPay\Core\Controller\Payment
 *
 * @see base_url/upstreampay/payment/notification
 */
class Notification implements CsrfAwareActionInterface, HttpPostActionInterface
{
    public const URL_PATH = 'upstreampay/payment/notification';

    /**
     * @param RequestInterface $request
     * @param LoggerInterface $logger
     * @param Config $config
     * @param NotificationService $notificationService
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly LoggerInterface $logger,
        private readonly Config $config,
        private readonly NotificationService $notificationService,
        private readonly JsonFactory $jsonFactory
    ) {}

    /**
     * @inheritDoc
     */
    public function execute()
    {
        $notification = json_decode($this->request->getContent(), true, 512);

        if ($this->config->getIsDebugEnabled()) {
            $this->logger->debug('Incoming notification:');
            $this->logger->debug(print_r($notification, true));
        }

        $this->notificationService->execute($notification);

        $resultJson = $this->jsonFactory->create();

        return $resultJson;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Validate the authenticity of the request.
     *
     * @param RequestInterface $request
     *
     * @return null|bool
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        $publicKey = PublicKeyLoader::loadPublicKey($this->config->getRsaKey());
        $privateKey = $publicKey->asPrivateKey()->withHash('sha1')->withMGFHash('sha1');
        $originalRequestHash = $privateKey->decrypt(base64_decode($request->getHeader('X-Signature')));
        $calculatedRequestHash = hash("sha256", $request->getContent());

        return $originalRequestHash === $calculatedRequestHash;
    }
}
