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
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use UpStreamPay\Core\Api\Data\OrderTransactionsInterface;
use UpStreamPay\Core\Api\Data\OrderTransactionsSearchResultsInterface;
use UpStreamPay\Core\Api\OrderTransactionsRepositoryInterface;
use UpStreamPay\Core\Model\ResourceModel\OrderTransactions as ResourceModel;
use UpStreamPay\Core\Model\ResourceModel\OrderTransactions\CollectionFactory;

/**
 * Class OrderTransactionsRepository
 *
 * @package UpStreamPay\Core\Model
 */
class OrderTransactionsRepository implements OrderTransactionsRepositoryInterface
{
    public function __construct(
        private readonly ResourceModel $resourceModel,
        private readonly OrderTransactionsFactory $orderTransactionsFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly OrderTransactionsSearchResultsFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(OrderTransactionsInterface $orderTransaction): OrderTransactionsInterface
    {
        try {
            $this->resourceModel->save($orderTransaction);
        } catch (Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        return $orderTransaction;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $entityId): OrderTransactionsInterface
    {
        /** @var OrderTransactionsInterface $orderTransaction */
        $orderTransaction = $this->orderTransactionsFactory->create();
        $this->resourceModel->load($orderTransaction, $entityId);

        if (!$orderTransaction->getId()) {
            throw new NoSuchEntityException(__('Order transaction with ID "%1" does not exist.', $entityId));
        }

        return $orderTransaction;
    }

    /**
     * @inheritDoc
     */
    public function getByTransactionId(string $transactionId): OrderTransactionsInterface
    {
        /** @var OrderTransactionsInterface $orderTransaction */
        $orderTransaction = $this->orderTransactionsFactory->create();
        $this->resourceModel->load($orderTransaction, $transactionId, OrderTransactionsInterface::TRANSACTION_ID);

        return $orderTransaction;
    }

    /**
     * @inheritDoc
     */
    public function getByParentTransactionId(string $parentTransactionId): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(OrderTransactionsInterface::PARENT_TRANSACTION_ID, $parentTransactionId)
            ->create();

        return $this->getList($searchCriteria)->getItems();
    }

    /**
     * @inheritDoc
     */
    public function getByOrderId(int $orderId): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(OrderTransactionsInterface::ORDER_ID, $orderId)
            ->create();

        return $this->getList($searchCriteria)->getItems();
    }

    /**
     * @inheritDoc
     */
    public function getByQuoteId(int $quoteId): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(OrderTransactionsInterface::QUOTE_ID, $quoteId)
            ->create();

        return $this->getList($searchCriteria)->getItems();
    }

    /**
     * @inheritDoc
     */
    public function getByInvoiceId(int $invoiceId): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(OrderTransactionsInterface::INVOICE_ID, $invoiceId)
            ->create();

        return $this->getList($searchCriteria)->getItems();
    }

    /**
     * @inheritDoc
     */
    public function getByCreditmemoId(int $creditmemoId): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(OrderTransactionsInterface::CREDITMEMO_ID, $creditmemoId)
            ->create();

        return $this->getList($searchCriteria)->getItems();
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria = null): OrderTransactionsSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();

        /** @var  $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        $this->collectionProcessor->process($searchCriteria, $collection);

        if ($searchCriteria->getPageSize()) {
            $searchResults->setTotalCount($collection->getSize());
        } else {
            $searchResults->setTotalCount(count($collection));
        }

        //Force type for IDE inspection.
        /** @var OrderTransactionsInterface[] $items */
        $items = $collection->getItems();
        $searchResults->setItems($items);

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(OrderTransactionsInterface $orderTransactions): bool
    {
        try {
            $this->resourceModel->delete($orderTransactions);
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
        $orderTransactions = $this->getById($entityId);

        return $this->delete($orderTransactions);
    }
}
