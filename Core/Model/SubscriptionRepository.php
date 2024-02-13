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
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use UpStreamPay\Core\Api\Data\SubscriptionInterface;
use UpStreamPay\Core\Api\Data\SubscriptionSearchResultsInterface;
use UpStreamPay\Core\Api\SubscriptionRepositoryInterface;
use UpStreamPay\Core\Model\ResourceModel\Subscription as resourceModel;
use UpStreamPay\Core\Model\ResourceModel\Subscription\CollectionFactory;

/**
 * Class SubscriptionRepository
 *
 * @codeCoverageIgnore
 *
 * @package UpStreamPay\Core\Model
 */
class SubscriptionRepository implements SubscriptionRepositoryInterface
{
    /**
     * @param resourceModel $resourceModel
     * @param SubscriptionFactory $subscriptionFactory
     * @param CollectionFactory $collectionFactory
     * @param SubscriptionSearchResultsFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly resourceModel                    $resourceModel,
        private readonly SubscriptionFactory              $subscriptionFactory,
        private readonly CollectionFactory                $collectionFactory,
        private readonly SubscriptionSearchResultsFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface     $collectionProcessor
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function save(SubscriptionInterface $subscription): SubscriptionInterface
    {
        try {
            $this->resourceModel->save($subscription);
        } catch (Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        return $subscription;
    }


    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SubscriptionSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();

        /** @var SubscriptionSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        $this->collectionProcessor->process($searchCriteria, $collection);

        if ($searchCriteria->getPageSize()) {
            $searchResults->setTotalCount($collection->getSize());
        } else {
            $searchResults->setTotalCount(count($collection));
        }

        //Force type for IDE inspection.
        /** @var SubscriptionInterface[] $items */
        $items = $collection->getItems();
        $searchResults->setItems($items);

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(SubscriptionInterface $subscription): bool
    {
        try {
            $this->resourceModel->delete($subscription);
        } catch (Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $entityId): SubscriptionInterface
    {
        /** @var SubscriptionInterface $subscription */
        $subscription = $this->subscriptionFactory->create();
        $this->resourceModel->load($subscription, $entityId);

        if (!$subscription->getId()) {
            throw new NoSuchEntityException(__(
                    'The UpStream Pay subscription with the "%1" ID doesn\'t exist.',
                    $entityId)
            );
        }

        return $subscription;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $entityId): bool
    {
        return $this->delete($this->getById($entityId));
    }
}
