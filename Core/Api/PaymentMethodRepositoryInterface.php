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
namespace UpStreamPay\Core\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use UpStreamPay\Core\Api\Data\PaymentMethodInterface;
use UpStreamPay\Core\Api\Data\PaymentMethodSearchResultsInterface;

/**
 * Interface PaymentMethodRepositoryInterface
 *
 * @package UpStreamPay\Core\Api
 */
interface PaymentMethodRepositoryInterface
{
    /**
     * @param PaymentMethodInterface $paymentMethod
     *
     * @return PaymentMethodInterface
     * @throws LocalizedException
     */
    public function save(PaymentMethodInterface $paymentMethod): PaymentMethodInterface;

    /**
     * @param int $entityId
     *
     * @return PaymentMethodInterface
     * @throws LocalizedException
     */
    public function getById(int $entityId): PaymentMethodInterface;

    /**
     * @param string $method
     *
     * @return PaymentMethodInterface
     * @throws LocalizedException
     */
    public function getByMethod(string $method): PaymentMethodInterface;

    /**
     * @param SearchCriteriaInterface $searchCriteria
     *
     * @return PaymentMethodSearchResultsInterface
     * @throws LocalizedException
     */
    public function getList(SearchCriteriaInterface $searchCriteria): PaymentMethodSearchResultsInterface;

    /**
     * @param PaymentMethodInterface $paymentMethod
     *
     * @return bool
     * @throws LocalizedException
     */
    public function delete(PaymentMethodInterface $paymentMethod): bool;

    /**
     * @param int $entityId
     *
     * @return bool
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $entityId): bool;
}
