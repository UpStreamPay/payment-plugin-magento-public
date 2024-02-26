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

namespace UpStreamPay\Core\Model;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use UpStreamPay\Core\Api\Data\SubscriptionRetryInterface;
use UpStreamPay\Core\Model\ResourceModel\SubscriptionRetry as SubscriptionRetryResource;

/**
 * Class OrderPayment
 *
 * @package UpStreamPay\Core\Model
 */
class SubscriptionRetry extends AbstractExtensibleModel implements SubscriptionRetryInterface
{
    //This means that the retry has returned an error and can be retried if the max number of retry hasn't been reached.
    public const ERROR_STATUS = 'error';
    //This means that the retry was a success and there is no need to retry payment on it again.
    public const SUCCESS_STATUS = 'success';
    //This means that the retry is in progress. Will transform into error, success or failure.
    public const WAITING_STATUS = 'waiting';
    //This means that we reached the maximum number of retry allowed.
    public const FAILURE_STATUS = 'failure';

    /**
     * @codeCoverageIgnore
     *
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param Config $config
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * @inheritDoc
     *
     * @codeCoverageIgnore
     */
    protected function _construct()
    {
        $this->_init(SubscriptionRetryResource::class);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getId(): ?int
    {
        return (int)$this->getData(SubscriptionRetryInterface::ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getSubscriptionId(): ?int
    {
        return $this->getData(SubscriptionRetryInterface::SUBSCRIPTION_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setSubscriptionId(int $subscriptionId): SubscriptionRetryInterface
    {
       return $this->setData(SubscriptionRetryInterface::SUBSCRIPTION_ID, $subscriptionId);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getNumberOfRetries(): ?int
    {
        return $this->getData(SubscriptionRetryInterface::NUMBER_OF_RETRIES);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setNumberOfRetries(int $number): SubscriptionRetryInterface
    {
        return $this->setData(SubscriptionRetryInterface::NUMBER_OF_RETRIES, $number);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getRetryStatus(): string
    {
        return $this->getData(SubscriptionRetryInterface::RETRY_STATUS);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setRetryStatus(string $status): SubscriptionRetryInterface
    {
        return $this->setData(SubscriptionRetryInterface::RETRY_STATUS, $status);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getRetryType(): ?string
    {
        return $this->getData(SubscriptionRetryInterface::RETRY_TYPE);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setRetryType(string $type): SubscriptionRetryInterface
    {
        return $this->setData(SubscriptionRetryInterface::RETRY_TYPE, $type);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function getTransactionId(): ?int
    {
        return $this->getData(SubscriptionRetryInterface::TRANSACTION_ID);
    }

    /**
     * @codeCoverageIgnore
     *
     * @inheritDoc
     */
    public function setTransactionId(int $transactionId): SubscriptionRetryInterface
    {
        return $this->setData(SubscriptionRetryInterface::TRANSACTION_ID, $transactionId);
    }

    /**
     * Return true if the retry can be performed, false otherwise.
     *
     * @return bool
     */
    public function canBeRetried(): bool
    {
        return $this->getRetryStatus() === self::ERROR_STATUS
            && $this->getNumberOfRetries() < $this->config->getSubscriptionPaymentMaximumPaymentRetry();
    }
}
