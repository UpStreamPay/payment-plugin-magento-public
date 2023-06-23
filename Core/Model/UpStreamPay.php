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

namespace UpStreamPay\Core\Model;

use Magento\Directory\Helper\Data as DirectoryHelper;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Payment\Helper\Data;
use Magento\Payment\Model\InfoInterface;
use Magento\Payment\Model\Method\AbstractMethod;
use Magento\Payment\Model\Method\Logger;
use Throwable;
use UpStreamPay\Client\Exception\NoOrderFoundException;
use UpStreamPay\Core\Model\Synchronize\OrderSynchronizeService;

/**
 * Class UpStreamPay
 *
 * @package Model
 */
class UpStreamPay extends AbstractMethod
{
    protected $_code = Config::METHOD_CODE_UPSTREAM_PAY;

    protected $_isGateway = true;

    protected $_canOrder = true;

    protected $_canAuthorize = true;

    protected $_canCapture = true;
    protected $_canCapturePartial = true;

    protected $_canRefund = true;
    protected $_canRefundInvoicePartial = true;

    protected $_canVoid = true;

    protected $_canUseCheckout = true;

    protected $_canUseInternal = false;

    protected $_canFetchTransactionInfo = false;

    protected $_canReviewPayment = true;

    /**
     * @param OrderSynchronizeService $orderSynchronizeService
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param Data $paymentData
     * @param ScopeConfigInterface $scopeConfig
     * @param Logger $logger
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     * @param DirectoryHelper|null $directory
     */
    public function __construct(
        private readonly OrderSynchronizeService $orderSynchronizeService,
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        Data $paymentData,
        ScopeConfigInterface $scopeConfig,
        Logger $logger,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = [],
        DirectoryHelper $directory = null
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $paymentData,
            $scopeConfig,
            $logger,
            $resource,
            $resourceCollection,
            $data,
            $directory
        );
    }

    /**
     * @return string
     */
    public function getPaymentAction(): string
    {
        return AbstractMethod::ACTION_AUTHORIZE_CAPTURE;
    }

    public function authorize(InfoInterface $payment, $amount)
    {
        $payment->setIsTransactionPending(true);

        return $this;
    }

    /**
     * Capture the order.
     *
     * We capture using custom functions, this has no special logic.
     *
     *
     * @param InfoInterface $payment
     * @param $amount
     *
     * @return $this|UpStreamPay
     */
    public function capture(InfoInterface $payment, $amount)
    {
        try {
            //@TODO we should pass the amount to capture in the future.
            //@TODO for now this is WIP & will be dealt with later when we do partial capture.
            //On initial place order this will always throw an exception because UpStream Pay doesnt have the data yet.
            //Initial capture is done after redirection or through webhook.
            $this->orderSynchronizeService->synchronizeAndCapture($payment->getOrder());
        } catch (NoOrderFoundException $exception) {
            //No order found because capture is done before UpStream Pay has the order.
            //No operation has been done so nothing to cancel or refund.
            $payment->setIsTransactionPending(true);

            return $this;
        } catch (Throwable $exception) {
            //Handle errors better.
            $payment->setIsTransactionPending(true);
            $payment->setIsTransactionApproved(false);

            return $this;
        }

        $payment->setIsTransactionPending(false);
        $payment->setIsTransactionApproved(true);

        return $this;
    }
}
