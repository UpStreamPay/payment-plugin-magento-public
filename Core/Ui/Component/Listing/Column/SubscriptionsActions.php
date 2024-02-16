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

namespace UpstreamPay\Core\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use UpStreamPay\Core\Model\Subscription;
use UpStreamPay\Core\Model\SubscriptionRepository;

/**
 * Class SubscriptionsActions
 *
 * Subscription actions column
 */
class SubscriptionsActions extends Column
{
    /**
     * Url path
     */
    const URL_PATH_CANCEL = 'core/subscriptions/cancel';


    /**
     * Constructor
     *
     * @param SubscriptionRepository $subscriptionRepository
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param Escaper $escaper
     * @param array $components
     * @param array $data
     */
    public function __construct(
        private readonly SubscriptionRepository $subscriptionRepository,
        ContextInterface                        $context,
        UiComponentFactory                      $uiComponentFactory,
        private readonly UrlInterface           $urlBuilder,
        private readonly Escaper                $escaper,
        array                                   $components = [],
        array                                   $data = []
    )
    {

        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     * @throws LocalizedException
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                if (isset($item['entity_id'])) {
                    /** @var Subscription $subscription */
                    $subscription = $this->subscriptionRepository->getById((int)$item['entity_id']);
                    if ($subscription->canCancel()) {
                        $item[$this->getData('name')]['cancel'] = [
                            'href' => $this->urlBuilder->getUrl(
                                static::URL_PATH_CANCEL,
                                [
                                    'id' => $item['entity_id']
                                ]
                            ),
                            'label' => __('Cancel'),
                            'confirm' => [
                                'title' => __(
                                    'Cancel %1',
                                    $this->escaper->escapeHtml($item['subscription_identifier'])
                                ),
                                'message' => __(
                                    'Are you sure to cancel the %1 subscription ?',
                                    $this->escaper->escapeHtml($item['subscription_identifier'])
                                )
                            ],
                            'post' => true,
                        ];
                    }

                }
            }
        }

        return $dataSource;
    }
}
