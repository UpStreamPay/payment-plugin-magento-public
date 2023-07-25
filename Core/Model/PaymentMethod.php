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
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use UpStreamPay\Core\Api\Data\PaymentMethodInterface;
use UpStreamPay\Core\Api\PaymentMethodRepositoryInterface;
use UpStreamPay\Core\Model\ResourceModel\OrderPayment as OrderPaymentResourceModel;
use Magento\Framework\Event\ManagerInterface as EventManager;

/**
 * Class PaymentMethod
 *
 * @package UpStreamPay\Core\Model
 */
class PaymentMethod extends AbstractExtensibleModel implements PaymentMethodInterface
{
    public const PRIMARY = 'primary';
    public const SECONDARY = 'secondary';

    protected $_eventPrefix = 'upstream_pay_payment_method';
    protected $_eventObject = 'payment_method';

    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        $this->_init(OrderPaymentResourceModel::class);
    }

    /**
     * @param PaymentMethodRepositoryInterface $paymentMethodRepository
     * @param PaymentMethodFactory $paymentMethodFactory
     * @param EventManager $eventManager
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        private readonly PaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly PaymentMethodFactory $paymentMethodFactory,
        private readonly EventManager $eventManager,
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
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
     */
    public function getEntityId(): ?int
    {
        return (int)$this->getData(self::ENTITY_ID);
    }

    /**
     * @inheritDoc
     */
    public function getMethod(): string
    {
        return $this->getData(PaymentMethodInterface::METHOD);
    }

    /**
     * @inheritDoc
     */
    public function setMethod(string $method): PaymentMethodInterface
    {
        return $this->setData(PaymentMethodInterface::METHOD, $method);
    }

    /**
     * @inheritDoc
     */
    public function getType(): string
    {
        return $this->getData(PaymentMethodInterface::TYPE);
    }

    /**
     * @inheritDoc
     */
    public function setType(string $type): PaymentMethodInterface
    {
        return $this->setData(PaymentMethodInterface::TYPE, $type);
    }

    /**
     * @inheritDoc
     */
    public function getCreatedAt(): string
    {
        return $this->getData(PaymentMethodInterface::CREATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setCreatedAt(string $createdAt): PaymentMethodInterface
    {
        return $this->setData(PaymentMethodInterface::CREATED_AT, $createdAt);
    }

    /**
     * @inheritDoc
     */
    public function getUpdatedAt(): string
    {
        return $this->getData(PaymentMethodInterface::UPDATED_AT);
    }

    /**
     * @inheritDoc
     */
    public function setUpdatedAt(string $updatedAt): PaymentMethodInterface
    {
        return $this->setData(PaymentMethodInterface::UPDATED_AT, $updatedAt);
    }

    /**
     * Create or update the payment method.
     *
     * @param string $method
     * @param string $type
     *
     * @return PaymentMethodInterface
     * @throws LocalizedException
     */
    public function updateOrCreate(string $method, string $type): PaymentMethodInterface
    {
        $this->eventManager->dispatch('payment_usp_write_method', ['method' => $method, 'type' => $type]);

        $paymentMethod = $this->paymentMethodRepository->getByMethod($method);

        if ($paymentMethod && $paymentMethod->getEntityId()) {
            if ($paymentMethod->getType() !== $type) {
                //Payment method exists but the type changed, update.
                $paymentMethod->setType($type);

                return $this->paymentMethodRepository->save($paymentMethod);
            }

            return $paymentMethod;
        } else {
            return $this->createPaymentMethod($method, $type);
        }
    }

    /**
     * Create the payment method.
     *
     * @param string $method
     * @param string $type
     *
     * @return PaymentMethodInterface
     * @throws LocalizedException
     */
    private function createPaymentMethod(string $method, string $type): PaymentMethodInterface
    {
        $orderPayment = $this->paymentMethodFactory->create();

        $orderPayment
            ->setMethod($method)
            ->setType($type)
        ;

        return $this->paymentMethodRepository->save($orderPayment);
    }
}
