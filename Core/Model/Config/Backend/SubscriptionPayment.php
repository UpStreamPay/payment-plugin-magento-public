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

namespace UpStreamPay\Core\Model\Config\Backend;

use Magento\Cron\Model\Config\Source\Frequency;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\App\Config\ValueFactory;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;

/**
 * Class SubscriptionPayment
 *
 * @package UpStreamPay\Core\Model\Config\Backend
 */
class SubscriptionPayment extends Value
{
    public const CRON_STRING_PATH = 'crontab/default/jobs/subscription_payment/schedule/cron_expr';
    public const CRON_MODEL_PATH = 'crontab/default/jobs/subscription_payment/run/model';

    //TODO move the 2 consts into config model.
    public const XML_PATH_SUBSCRIPTION_PAYMENT_FREQUENCY = 'payment/upstream_pay/subscription_payment/frequency';
    public const XML_PATH_SUBSCRIPTION_PAYMENT_TIME = 'payment/upstream_pay/subscription_payment/time';

    /**
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param ValueFactory $configValueFactory
     * @param null|AbstractResource $resource
     * @param null|AbstractDb $resourceCollection
     * @param string $runModelPath
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly ValueFactory $configValueFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        private readonly string $runModelPath = '',
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * After save handler
     *
     * @return $this
     * @throws LocalizedException
     */
    public function afterSave(): Value
    {
        //TODO replace 2 consts with const from config model.
        $time = explode(',', $this->_config->getValue(self::XML_PATH_SUBSCRIPTION_PAYMENT_TIME));
        $frequency = $this->_config->getValue(self::XML_PATH_SUBSCRIPTION_PAYMENT_FREQUENCY);
        $frequencyWeekly = Frequency::CRON_WEEKLY;
        $frequencyMonthly = Frequency::CRON_MONTHLY;

        $cronExprArray = [
            (int) $time[1],                                 # Minute
            (int) $time[0],                                 # Hour
            $frequency == $frequencyMonthly ? '1' : '*',      # Day of the Month
            '*',                                              # Month of the Year
            $frequency == $frequencyWeekly ? '1' : '*',        # Day of the Week
        ];
        $cronExprString = join(' ', $cronExprArray);

        try {
            $this->configValueFactory->create()->load(
                self::CRON_STRING_PATH,
                'path'
            )->setValue(
                $cronExprString
            )->setPath(
                self::CRON_STRING_PATH
            )->save();
            $this->configValueFactory->create()->load(
                self::CRON_MODEL_PATH,
                'path'
            )->setValue(
                $this->runModelPath
            )->setPath(
                self::CRON_MODEL_PATH
            )->save();
        } catch (\Exception $e) {
            throw new LocalizedException(__('We can\'t save the cron expression.'));
        }
        return parent::afterSave();
    }
}
