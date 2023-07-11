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
use Magento\Framework\Exception\NoSuchEntityException;
use UpStreamPay\Core\Api\Data\OrderPaymentInterface;
use UpStreamPay\Core\Api\Data\OrderPaymentSearchResultsInterface;
use UpStreamPay\Core\Api\OrderPaymentRepositoryInterface;
use UpStreamPay\Core\Model\ResourceModel\OrderPayment as resourceModel;
use UpStreamPay\Core\Model\ResourceModel\OrderPayment\CollectionFactory;

/**
 * Class OrderPaymentRepository
 *
 * @package UpStreamPay\Core\Model
 */
class OrderPaymentRepository implements OrderPaymentRepositoryInterface
{
    public function __construct(
        private readonly resourceModel $resourceModel,
        private readonly OrderPaymentFactory $orderPaymentFactory,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly CollectionFactory $collectionFactory,
        private readonly OrderPaymentSearchResultsFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     */
    public function save(OrderPaymentInterface $orderPayment): OrderPaymentInterface
    {
        try {
            $this->resourceModel->save($orderPayment);
        } catch (Exception $exception) {
            throw new CouldNotSaveException(__($exception->getMessage()));
        }

        return $orderPayment;
    }

    /**
     * @inheritDoc
     */
    public function getById(int $orderPaymentId): OrderPaymentInterface
    {
        /** @var OrderPaymentInterface $orderPayment */
        $orderPayment = $this->orderPaymentFactory->create();
        $this->resourceModel->load($orderPayment, $orderPaymentId);

        if (!$orderPayment->getId()) {
            throw new NoSuchEntityException(__(
                'The UpStream Pay order payment with the "%1" ID doesn\'t exist.',
                    $orderPaymentId)
            );
        }

        return $orderPayment;
    }

    /**
     * @inheritDoc
     */
    public function getByQuoteId(int $quoteId): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(OrderPaymentInterface::QUOTE_ID, $quoteId)
            ->create();

        return $this->getList($searchCriteria)->getItems();
    }

    /**
     * @inheritDoc
     */
    public function getBySessionId(string $sessionId): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(OrderPaymentInterface::SESSION_ID, $sessionId)
            ->create();

        return $this->getList($searchCriteria)->getItems();
    }

    /**
     * @inheritDoc
     */
    public function getByOrderId(int $orderId): array
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(OrderPaymentInterface::ORDER_ID, $orderId)
            ->create();

        return $this->getList($searchCriteria)->getItems();
    }

    /**
     * @inheritDoc
     */
    public function getByDefaultTransactionId(string $defaultTransactionId): OrderPaymentInterface
    {
        /** @var OrderPaymentInterface $orderPayment */
        $orderPayment = $this->orderPaymentFactory->create();
        $this->resourceModel->load($orderPayment, $defaultTransactionId, OrderPaymentInterface::DEFAULT_TRANSACTION_ID);

        return $orderPayment;
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $searchCriteria): OrderPaymentSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();

        /** @var OrderPaymentSearchResultsInterface $searchResults */
        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);

        $this->collectionProcessor->process($searchCriteria, $collection);

        if ($searchCriteria->getPageSize()) {
            $searchResults->setTotalCount($collection->getSize());
        } else {
            $searchResults->setTotalCount(count($collection));
        }

        //Force type for IDE inspection.
        /** @var OrderPaymentInterface[] $items */
        $items = $collection->getItems();
        $searchResults->setItems($items);

        return $searchResults;
    }

    /**
     * @inheritDoc
     */
    public function delete(OrderPaymentInterface $orderPayment): bool
    {
        try {
            $this->resourceModel->delete($orderPayment);
        } catch (Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById(int $orderPaymentId): bool
    {
        return $this->delete($this->getById($orderPaymentId));
    }
}
