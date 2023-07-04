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

use Exception;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use UpStreamPay\Core\Api\Data\PaymentMethodInterface;
use UpStreamPay\Core\Api\Data\PaymentMethodSearchResultsInterface;
use UpStreamPay\Core\Api\PaymentMethodRepositoryInterface;
use UpStreamPay\Core\Model\ResourceModel\PaymentMethod as ResourceModel;
use UpStreamPay\Core\Model\ResourceModel\PaymentMethod\CollectionFactory;

/**
 * Class PaymentMethodRepository
 *
 * @package UpStreamPay\Core\Model
 */
class PaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    /**
     * @param ResourceModel $resourceModel
     * @param PaymentMethodFactory $paymentMethodFactory
     * @param CollectionFactory $collectionFactory
     * @param PaymentMethodSearchResultsFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly ResourceModel $resourceModel,
        private readonly PaymentMethodFactory $paymentMethodFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly PaymentMethodSearchResultsFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(PaymentMethodInterface $paymentMethod): PaymentMethodInterface
    {
        try {
            $this->resourceModel->save($paymentMethod);
        } catch (Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        return $paymentMethod;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $entityId): PaymentMethodInterface
    {
        /** @var PaymentMethodInterface $orderTransaction */
        $orderTransaction = $this->paymentMethodFactory->create();
        $this->resourceModel->load($orderTransaction, $entityId);

        if (!$orderTransaction->getId()) {
            throw new NoSuchEntityException(__('Order transaction with ID "%1" does not exist.', $entityId));
        }

        return $orderTransaction;
    }

    /**
     * @inheritDoc
     */
    public function getByMethod(string $method): PaymentMethodInterface
    {
        /** @var PaymentMethodInterface $paymentMethod */
        $paymentMethod = $this->paymentMethodFactory->create();
        $this->resourceModel->load($paymentMethod, $method, PaymentMethodInterface::METHOD);

        return $paymentMethod;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): PaymentMethodSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();

        /** @var PaymentMethodSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        $this->collectionProcessor->process($searchCriteria, $collection);

        if ($searchCriteria->getPageSize()) {
            $searchResults->setTotalCount($collection->getSize());
        } else {
            $searchResults->setTotalCount(count($collection));
        }

        //Force type for IDE inspection.
        /** @var PaymentMethodInterface[] $items */
        $items = $collection->getItems();
        $searchResults->setItems($items);

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(PaymentMethodInterface $paymentMethod): bool
    {
        try {
            $this->resourceModel->delete($paymentMethod);
        } catch (Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $entityId): bool
    {
        $paymentMethod = $this->getById($entityId);

        return $this->delete($paymentMethod);
    }
}
