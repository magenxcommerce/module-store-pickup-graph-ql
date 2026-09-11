<?php
/**
 * Copyright © Magenx. All rights reserved.
 */
declare(strict_types=1);

namespace Magenx\StorePickupGraphQl\Observer;

use Magenx\StorePickupGraphQl\Model\Config;
use Magento\Directory\Model\RegionFactory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Magento\Sales\Api\Data\OrderAddressInterface;

/**
 * At order placement (event: sales_model_service_quote_submit_before), and only
 * when the order is genuinely being collected — see {@see isPickupSelected}:
 *
 *  1. Copies the pickup-location code from the quote onto the order, so the
 *     fulfilling store is recorded on the sales order.
 *  2. Overwrites the order's shipping address with the chosen store's address,
 *     so the order — and the invoice/shipment derived from it — ships to the
 *     pickup store rather than to the shopper's own address. The shopper is
 *     kept as the recipient (firstname / lastname / email) so store staff know
 *     who is collecting; the store name becomes the address company.
 */
class CopyPickupLocationToOrder implements ObserverInterface
{
    /**
     * @param Config $config
     * @param RegionFactory $regionFactory
     */
    public function __construct(
        private readonly Config $config,
        private readonly RegionFactory $regionFactory
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $order = $observer->getEvent()->getData('order');
        $quote = $observer->getEvent()->getData('quote');
        if ($order === null || !$quote instanceof Quote) {
            return;
        }

        $code = $quote->getData('magenx_pickup_location_code');
        if ($code === null || $code === '') {
            return;
        }

        if (!$this->isPickupSelected($quote)) {
            return;
        }

        $order->setData('magenx_pickup_location_code', $code);

        $location = $this->config->getLocationByCode((string) $code, (int) $order->getStoreId());
        if ($location === null) {
            return;
        }

        $shippingAddress = $order->getShippingAddress();
        if ($shippingAddress === null) {
            return;
        }

        $this->applyLocationToAddress($shippingAddress, $location);
    }

    /**
     * Whether the order being placed actually uses the in-store pickup carrier.
     *
     * A quote can carry a stale pickup code: the location and the shipping
     * method are set by two independent mutations, and nothing in the schema
     * clears one when the other changes. Without this check a shopper who
     * selected pickup, changed their mind and chose a delivery carrier would
     * still have the order's shipping address rewritten to the store — and the
     * parcel would ship to the store rather than to them.
     *
     * The quote's shipping address is the source the order's own shipping
     * method was converted from moments earlier, so it is read here rather than
     * the half-built order. Matched on the carrier prefix rather than the full
     * `carrier_method` string so a future method-code change cannot silently
     * disable the guard. Virtual quotes have no shipping method and are
     * therefore excluded, which is correct: there is nothing to collect.
     *
     * @param Quote $quote
     * @return bool
     */
    private function isPickupSelected(Quote $quote): bool
    {
        if ($quote->isVirtual()) {
            return false;
        }

        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress === null) {
            return false;
        }

        $method = (string) $shippingAddress->getShippingMethod();

        return $method !== '' && str_starts_with($method, Config::CARRIER_CODE . '_');
    }

    /**
     * Replace the address' location fields with the pickup store's, preserving
     * the recipient's identity (name / email).
     *
     * @param OrderAddressInterface $address
     * @param array<string, mixed> $location
     * @return void
     */
    private function applyLocationToAddress(OrderAddressInterface $address, array $location): void
    {
        $countryId = trim((string) ($location['country_id'] ?? ''));

        $address->setCompany(trim((string) ($location['name'] ?? '')));

        // The config stores street as a single (possibly multi-line) string;
        // pass it through verbatim so newlines are preserved and no PHP array is
        // ever written to the address' street column.
        $address->setStreet(trim((string) ($location['street'] ?? '')));

        $address->setCity(trim((string) ($location['city'] ?? '')));
        $address->setPostcode(trim((string) ($location['postcode'] ?? '')));

        if ($countryId !== '') {
            $address->setCountryId($countryId);
        }

        $phone = trim((string) ($location['phone'] ?? ''));
        if ($phone !== '') {
            $address->setTelephone($phone);
        }

        $this->applyRegion($address, trim((string) ($location['region'] ?? '')), $countryId);
    }

    /**
     * Resolve the store's region string to a region_id where possible so the
     * order address is complete for region-required countries; fall back to the
     * free-text region when it cannot be resolved.
     *
     * @param OrderAddressInterface $address
     * @param string $region
     * @param string $countryId
     * @return void
     */
    private function applyRegion(OrderAddressInterface $address, string $region, string $countryId): void
    {
        if ($region === '') {
            $address->setRegion(null);
            $address->setRegionId(null);
            return;
        }

        if ($countryId !== '') {
            $regionModel = $this->regionFactory->create();
            $regionModel->loadByCode($region, $countryId);
            if (!$regionModel->getId()) {
                $regionModel->loadByName($region, $countryId);
            }
            if ($regionModel->getId()) {
                $address->setRegion($regionModel->getName());
                $address->setRegionId((int) $regionModel->getId());
                return;
            }
        }

        $address->setRegion($region);
        $address->setRegionId(null);
    }
}
