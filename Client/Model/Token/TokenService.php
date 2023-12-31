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

namespace UpStreamPay\Client\Model\Token;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use UpStreamPay\Client\Api\Data\TokenInterface;
use UpStreamPay\Client\Model\Cache\Type\UpStreamPay;

/**
 * Class TokenService
 *
 * @package UpstreamPay\Client\Model\Token
 */
class TokenService
{
    /**
     * @param TokenFactory $tokenFactory
     * @param TimezoneInterface $timezone
     * @param CacheInterface $cache
     * @param SerializerInterface $serializer
     */
    public function __construct(
        private readonly TokenFactory $tokenFactory,
        private readonly TimezoneInterface $timezone,
        private readonly CacheInterface $cache,
        private readonly SerializerInterface $serializer
    ) {
    }

    /**
     * Get the token from the cache.
     * Return a token with no values in case there is no token in cache.
     *
     * @return TokenInterface
     */
    public function getToken(): TokenInterface
    {
        /** @var TokenInterface $token */
        $token = $this->tokenFactory->create();

        $cachedToken = $this->cache->load(UpStreamPay::TYPE_IDENTIFIER);

        if (!$cachedToken) {
            return $token;
        }

        $rawToken = $this->serializer->unserialize($cachedToken);

        if ($rawToken === null) {
            return $token;
        }

        return $token->setData($rawToken);
    }

    /**
     * Set a token in cache based on token data received from API & return a token object.
     *
     * In case the API sends back an incorrect result, throw exception & log critical error.
     *
     * @param array $tokenData
     *
     * @return TokenInterface
     */
    public function setToken(array $tokenData): TokenInterface
    {
        /** @var TokenInterface $token */
        $token = $this->tokenFactory->create();
        $createdAt = $this->timezone->date();

        //Substract 5 minutes of validity in order to account for request time, cpu time etc.
        //That way we are sure we will never send an expired token (that could be expired since 1 second).
        $tokenLifetimeInSeconds = $tokenData['expires_in'] - 300;

        $token
            ->setValue($tokenData['access_token'])
            ->setLifetime($tokenData['expires_in'])
            ->setCreatedAt($createdAt->format('Y-m-d H:i:s'))
            ->setExpirationDate($createdAt->modify("+$tokenLifetimeInSeconds seconds")->format('Y-m-d H:i:s'))
        ;

        $this->cache->save(
            $this->serializer->serialize($token->toArray()),
            UpStreamPay::CACHE_TAG,
            [UpStreamPay::CACHE_TAG],
            $token->getLifetime()
        );

        return $token;
    }
}
