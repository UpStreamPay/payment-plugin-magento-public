<?php
/**
 *  UpStream Pay
 *
 *  Copyright (c) 2023 UpStream Pay.
 *  This file is open source and available under the BSD 3 license.
 *  See the LICENSE file for more info.
 *
 * Author: Claranet France <info@fr.clara.net>
 */

declare(strict_types=1);

namespace UpStreamPay\Core\Model\Subscription\Renew;

use Magento\Catalog\Model\Product\Type;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\StateException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderInterface;
use UpStreamPay\Core\Exception\NoSubscriptionFoundInQuoteException;
use UpStreamPay\Core\Exception\SubmitRenewQuoteException;
use UpStreamPay\Core\Model\Config;

/**
 * Class CartManagement
 *
 * @package UpStreamPay\Core\Model\Subscription\Renew
 */
class CartManagement
{
    /**
     * @param CartRepositoryInterface $cartRepository
     * @param CartManagementInterface $cartManagement
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartManagementInterface $cartManagement,
    )
    {
    }

    /**
     * Create quote in order to renew a subscription
     *
     * @param string $productSku
     * @param int $originalQuoteId
     *
     * @return Quote
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws NoSubscriptionFoundInQuoteException
     * @throws NoSuchEntityException
     * @throws StateException
     */
    public function createQuote(string $productSku, int $originalQuoteId): Quote
    {
        $originalQuote = $this->cartRepository->get($originalQuoteId);
        $virtualProductFound = false;
        $subscriptionProductFound = false;
        $newQuote = null;

        //Add the wanted product to the quote with proper options etc.
        foreach ($originalQuote->getAllVisibleItems() as $item) {
            if ($item->getSku() === $productSku) {
                //Create the new quote only if we found the subscription product
                if ($newQuote === null) {
                    $newQuoteId = $this->cartManagement->createEmptyCart();
                    /** @var Quote $newQuote */
                    $newQuote = $this->cartRepository->get($newQuoteId);
                    $newQuote->setStoreId((int)$originalQuote->getStoreId());
                }

                if (!$virtualProductFound) {
                    $virtualProductFound = $item->getProductType() === Type::TYPE_VIRTUAL;
                }

                $options = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());
                $info = $options['info_buyRequest'];
                $request = new DataObject();
                $request->setData($info);

                $newItem = $newQuote->addProduct($item->getProduct(), $request);
                $newItem->setAdditionalData($item->getAdditionalData());
                $newItem->getProduct()->setIsSuperMode(true);
                $newItem->setQty($item->getQty());

                $subscriptionProductFound = true;

                break;
            }
        }

        //If there is no subscription product in the quote, then no need go any further.
        if (!$subscriptionProductFound) {
            throw new NoSubscriptionFoundInQuoteException();
        }

        //If customer is not a guest, then assign customer to the quote.
        if (!$originalQuote->getCustomerIsGuest()) {
            //In case we are dealing with a customer, we have to mark the active quote as inactive otherwise the newly
            //created quote will be merged with the active one, causing issues & creating quotes with more items than
            //needed.
            //Catch in case there are no active quote.
            try {
                $customerActiveQuote = $this->cartRepository->getForCustomer($originalQuote->getCustomerId());
                $customerActiveQuote->setIsActive(0);
                $this->cartRepository->save($customerActiveQuote);
            } catch (\Throwable) {
                //Silence is golden.
            }

            $this->cartManagement->assignCustomer(
                $newQuoteId,
                $originalQuote->getCustomerId(),
                $originalQuote->getStoreId()
            );
        } else {
            //Set all the guest info.
            $newQuote
                ->setCheckoutMethod($originalQuote->getCheckoutMethod())
                ->setCustomerEmail($originalQuote->getCustomerEmail())
                ->setCustomerPrefix($originalQuote->getCustomerPrefix())
                ->setCustomerFirstname($originalQuote->getCustomerFirstname())
                ->setCustomerMiddlename($originalQuote->getCustomerMiddlename())
                ->setCustomerLastname($originalQuote->getCustomerLastname())
                ->setCustomerDob($originalQuote->getCustomerDob())
                ->setCustomerSuffix($originalQuote->getCustomerSuffix())
                ->setCustomerGroupId($originalQuote->getCustomerGroupId())
            ;
        }

        $billingAddress = $originalQuote->getBillingAddress();
        $shippingAddress = $originalQuote->getShippingAddress();

        $newQuote->getBillingAddress()->setCustomerAddressId($billingAddress->getCustomerAddressId());
        $newQuote->getBillingAddress()->setSaveInAddressBook($billingAddress->getSaveInAddressBook());
        $newQuote->getBillingAddress()->setEmail($billingAddress->getEmail());
        $newQuote->getBillingAddress()->setPrefix($billingAddress->getPrefix());
        $newQuote->getBillingAddress()->setFirstname($billingAddress->getFirstname());
        $newQuote->getBillingAddress()->setMiddlename($billingAddress->getMiddlename());
        $newQuote->getBillingAddress()->setLastname($billingAddress->getLastname());
        $newQuote->getBillingAddress()->setSuffix($billingAddress->getSuffix());
        $newQuote->getBillingAddress()->setCompany($billingAddress->getCompany());
        $newQuote->getBillingAddress()->setStreet($billingAddress->getStreet());
        $newQuote->getBillingAddress()->setCity($billingAddress->getCity());
        $newQuote->getBillingAddress()->setRegion($billingAddress->getRegion());
        $newQuote->getBillingAddress()->setRegionId($billingAddress->getRegionId());
        $newQuote->getBillingAddress()->setPostcode($billingAddress->getPostcode());
        $newQuote->getBillingAddress()->setCountryId($billingAddress->getCountryId());
        $newQuote->getBillingAddress()->setTelephone($billingAddress->getTelephone());
        $newQuote->getBillingAddress()->setFax($billingAddress->getFax());

        //Very important check, so we can set up the shipping addresses properly.
        if (!$virtualProductFound) {
            $newQuote->getShippingAddress()->setCustomerAddressId($shippingAddress->getCustomerAddressId());
            $newQuote->getShippingAddress()->setSaveInAddressBook($shippingAddress->getSaveInAddressBook());
            $newQuote->getShippingAddress()->setEmail($shippingAddress->getEmail());
            $newQuote->getShippingAddress()->setPrefix($shippingAddress->getPrefix());
            $newQuote->getShippingAddress()->setFirstname($shippingAddress->getFirstname());
            $newQuote->getShippingAddress()->setMiddlename($shippingAddress->getMiddlename());
            $newQuote->getShippingAddress()->setLastname($shippingAddress->getLastname());
            $newQuote->getShippingAddress()->setSuffix($shippingAddress->getSuffix());
            $newQuote->getShippingAddress()->setCompany($shippingAddress->getCompany());
            $newQuote->getShippingAddress()->setStreet($shippingAddress->getStreet());
            $newQuote->getShippingAddress()->setCity($shippingAddress->getCity());
            $newQuote->getShippingAddress()->setRegion($shippingAddress->getRegion());
            $newQuote->getShippingAddress()->setRegionId($shippingAddress->getRegionId());
            $newQuote->getShippingAddress()->setPostcode($shippingAddress->getPostcode());
            $newQuote->getShippingAddress()->setCountryId($shippingAddress->getCountryId());
            $newQuote->getShippingAddress()->setTelephone($shippingAddress->getTelephone());
            $newQuote->getShippingAddress()->setFax($shippingAddress->getFax());
            $newQuote->getShippingAddress()->setSameAsBilling($shippingAddress->getSameAsBilling());
            $newQuote->getShippingAddress()->setShippingMethod($shippingAddress->getShippingMethod());
            $newQuote->getShippingAddress()->setShippingDescription($shippingAddress->getShippingDescription());
        } else {
            $newQuote->getShippingAddress()->setCustomerId($shippingAddress->getCustomerId());
        }

        $newQuote->setRemoteIp($originalQuote->getRemoteIp());

        $newQuote->setPaymentMethod(Config::METHOD_CODE_UPSTREAM_PAY);
        $newQuote->setInventoryProcessed(false);
        $newQuote->getPayment()->importData(['method' => Config::METHOD_CODE_UPSTREAM_PAY]);

        //Letting Magento know that this quote was not created by a customer.
        $newQuote->setIsSuperMode(true);

        $this->cartRepository->save($newQuote);

        //Collect shipping rates after initial quote save if there are no virtual products.
        if (!$virtualProductFound) {
            $newQuote->getShippingAddress()->setCollectShippingRates(true)->collectShippingRates();
        }

        //Very important to collect totals.
        $newQuote->setTotalsCollectedFlag(false);
        $newQuote->collectTotals();

        //Save the quote with all totals & shipping rates collected.
        $this->cartRepository->save($newQuote);

        return $newQuote;
    }

    /**
     * Create
     *
     * @param Quote $quote
     *
     * @return OrderInterface
     * @throws SubmitRenewQuoteException
     */
    public function submitQuote(Quote $quote): OrderInterface
    {
        try {
            return $this->cartManagement->submit($quote);
        } catch (\Throwable $exception) {
            $quote->setIsActive(0);
            $this->cartRepository->save($quote);

            throw new SubmitRenewQuoteException();
        }
    }
}
