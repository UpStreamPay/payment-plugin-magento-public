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
use Magento\Payment\Model\MethodInterface;
use UpStreamPay\Client\Exception\NoOrderFoundException;
use UpStreamPay\Core\Exception\NoTransactionsException;
use UpStreamPay\Core\Model\Actions\OrderActionCaptureService;
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
     * @param Config $config
     * @param OrderActionCaptureService $orderActionCaptureService
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
        private readonly Config $config,
        private readonly OrderActionCaptureService $orderActionCaptureService,
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
        return $this->config->getPaymentAction();
    }

    /**
     * Can authorize if payment method configured to do so.
     *
     * @return bool
     */
    public function canAuthorize(): bool
    {
        return $this->getPaymentAction() === MethodInterface::ACTION_AUTHORIZE;
    }

    /**
     * Can capture if payment method configured to do so.
     *
     * @return bool
     */
    public function canCapture(): bool
    {
        return $this->getPaymentAction() !== MethodInterface::ACTION_AUTHORIZE;
    }

    /**
     * @inheritDoc
     */
    public function authorize(InfoInterface $payment, $amount)
    {
        //Before we can verify anything, the transaction is pending.
        $payment->setIsTransactionPending(true);

        try {
            $this->orderSynchronizeService->execute($payment, $amount, OrderTransactions::AUTHORIZE_ACTION);
        } catch (NoOrderFoundException | NoTransactionsException $exception) {
            //No order found because authorize is done before UpStream Pay has the order.
            //No transaction has been found.

            //In this scenario nothing to void.
            $payment->setIsTransactionPending(true);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function capture(InfoInterface $payment, $amount)
    {
        //Before we can verify anything, the transaction is pending.
        $payment->setIsTransactionPending(true);

        try {
            if ($this->canOrder()) {
                //This is triggered in case of capture using order action mode.
                $this->orderSynchronizeService->execute($payment, $amount, OrderTransactions::ORDER_CAPTURE_ACTION);
            } else {
                //On initial place order this will always throw an exception because UpStream Pay doesn't have the data yet.
                //Initial capture is done after redirection or through webhook.
                $this->orderSynchronizeService->execute($payment, $amount, OrderTransactions::CAPTURE_ACTION);
            }
        } catch (NoOrderFoundException | NoTransactionsException $exception) {
            //No order found because capture is done before UpStream Pay has the order.
            //No operation has been done so nothing to void or refund.
            $payment->setIsTransactionPending(true);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function cancel(InfoInterface $payment)
    {
        $this->orderSynchronizeService->execute($payment, 0.00, OrderTransactions::VOID_ACTION);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function void(InfoInterface $payment)
    {
        $this->orderSynchronizeService->execute($payment, 0.00, OrderTransactions::VOID_ACTION);
        $payment->setIsTransactionDenied(true);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function denyPayment(InfoInterface $payment)
    {
        $this->orderSynchronizeService->execute($payment, 0.00, OrderTransactions::VOID_ACTION);
        $payment->setIsTransactionDenied(true);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function refund(InfoInterface $payment, $amount)
    {
        //Cast to float because yes, magento sends back a string here....
        $this->orderSynchronizeService->execute($payment, (float)$amount, OrderTransactions::REFUND_ACTION);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function canOrder()
    {
        return $this->getPaymentAction() === MethodInterface::ACTION_ORDER;
    }

    /**
     * @inheritDoc
     */
    public function order(InfoInterface $payment, $amount)
    {
        $payment->setIsTransactionPending(true);

        try {
            $this->orderSynchronizeService->execute($payment, $amount, OrderTransactions::ORDER_ACTION);
        } catch (NoOrderFoundException | NoTransactionsException $exception) {
            //No order found because order action is done before UpStream Pay has the order.
            //No operation has been done so nothing to void or refund.
            $payment->setIsTransactionPending(true);
        }

        return $this;
    }
}
