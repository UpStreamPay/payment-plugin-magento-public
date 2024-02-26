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

use Exception;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use UpStreamPay\Core\Api\Data\SubscriptionRetryInterface;
use UpStreamPay\Core\Api\Data\SubscriptionRetrySearchResultsInterface;
use UpStreamPay\Core\Api\SubscriptionRetryRepositoryInterface;
use UpStreamPay\Core\Model\ResourceModel\SubscriptionRetry as resourceModel;
use UpStreamPay\Core\Model\ResourceModel\SubscriptionRetry\CollectionFactory;

/**
 * Class SubscriptionRetryRepository
 *
 * @codeCoverageIgnore
 *
 * @package UpStreamPay\Core\Model
 */
class SubscriptionRetryRepository implements SubscriptionRetryRepositoryInterface
{
    /**
     * @param resourceModel $resourceModel
     * @param SubscriptionRetryFactory $subscriptionRetryFactory
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param CollectionFactory $collectionFactory
     * @param SubscriptionRetrySearchResultsFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly resourceModel $resourceModel,
        private readonly SubscriptionRetryFactory $subscriptionRetryFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly CollectionFactory $collectionFactory,
        private readonly SubscriptionRetrySearchResultsFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(SubscriptionRetryInterface $subscriptionRetry): SubscriptionRetryInterface
    {
        try {
            $this->resourceModel->save($subscriptionRetry);
        } catch (Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        return $subscriptionRetry;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $id): SubscriptionRetryInterface
    {
        /** @var SubscriptionRetryInterface $subscriptionRetry */
        $subscriptionRetry = $this->subscriptionRetryFactory->create();
        $this->resourceModel->load($subscriptionRetry, $id);

        if (!$subscriptionRetry->getId()) {
            throw new NoSuchEntityException(__(
                'The UpStream Pay subscription retry with the "%1" ID doesn\'t exist.',
                    $id)
            );
        }

        return $subscriptionRetry;
    }

    /**
     * @inheritDoc
     */
    public function getBySubscriptionId(int $subscriptionId): SubscriptionRetryInterface
    {
        /** @var SubscriptionRetryInterface $subscriptionRetry */
        $subscriptionRetry = $this->subscriptionRetryFactory->create();
        $this->resourceModel->load($subscriptionRetry, $subscriptionId, SubscriptionRetryInterface::SUBSCRIPTION_ID);

        return $subscriptionRetry;
    }

    /**
     * @inheritDoc
     */
    public function getByTransactionId(int $transactionId): SubscriptionRetryInterface
    {
        /** @var SubscriptionRetryInterface $subscriptionRetry */
        $subscriptionRetry = $this->subscriptionRetryFactory->create();
        $this->resourceModel->load($subscriptionRetry, $transactionId, SubscriptionRetryInterface::TRANSACTION_ID);

        return $subscriptionRetry;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SubscriptionRetrySearchResultsInterface
    {
        $collection = $this->collectionFactory->create();

        /** @var SubscriptionRetrySearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        $this->collectionProcessor->process($searchCriteria, $collection);

        if ($searchCriteria->getPageSize()) {
            $searchResults->setTotalCount($collection->getSize());
        } else {
            $searchResults->setTotalCount(count($collection));
        }

        //Force type for IDE inspection.
        /** @var SubscriptionRetryInterface[] $items */
        $items = $collection->getItems();
        $searchResults->setItems($items);

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(SubscriptionRetryInterface $subscriptionRetry): bool
    {
        try {
            $this->resourceModel->delete($subscriptionRetry);
        } catch (Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $id): bool
    {
        return $this->delete($this->getById($id));
    }

    /**
     * Get all the subscription to retry payment on. We can only retry with an error status.
     *
     * @return SubscriptionRetryInterface[]
     * @throws LocalizedException
     */
    public function getAllSubscriptionToRetryPayment(): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(SubscriptionRetryInterface::RETRY_STATUS, Subscription::ERROR)
            ->create()
        ;

        return $this->getList($searchCriteria)->getItems();
    }
}
