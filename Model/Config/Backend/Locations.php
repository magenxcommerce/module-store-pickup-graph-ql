<?php
/**
 * Copyright © Magenx. All rights reserved.
 */
declare(strict_types=1);

namespace Magenx\StorePickupGraphQl\Model\Config\Backend;

use Magento\Config\Model\Config\Backend\Serialized\ArraySerialized;
use Magento\Framework\Exception\LocalizedException;

/**
 * Backend model for the "Store Locations" field-array.
 *
 * Behaves exactly like the core ArraySerialized it extends, plus a save-time
 * guard: every configured location must name a country.
 *
 * A location without a country is not a cosmetic gap. At placement
 * {@see \Magenx\StorePickupGraphQl\Observer\CopyPickupLocationToOrder} rewrites
 * the order's shipping address to the store's, and a blank country leaves the
 * order address' `country_id` as whatever the shopper's address carried — or
 * NULL for an address that never had one. Downstream shipping extensions read
 * that column as a plain string (netresearch/module-shipping-core passes
 * `$shippingAddress->getCountryId()` straight into a `string` parameter), so a
 * NULL there is a TypeError that takes out the whole `customer { orders }`
 * GraphQL query, not just the shipping data. It also blocks region resolution:
 * a region code can only be matched against a country.
 *
 * Enforced here rather than only in the admin grid so `bin/magento config:set`
 * and any other save path is held to the same rule. Reads stay tolerant —
 * {@see \Magenx\StorePickupGraphQl\Model\Config} still returns rows saved
 * before this guard existed, so a legacy location keeps resolving on historical
 * orders instead of vanishing from them.
 */
class Locations extends ArraySerialized
{
    /**
     * The row fields that make a row "filled in" rather than an empty template.
     */
    private const ROW_FIELDS = [
        'code',
        'name',
        'street',
        'city',
        'region',
        'postcode',
        'country_id',
        'phone',
        'hours',
        'latitude',
        'longitude',
    ];

    /**
     * Reject any location row that has no country.
     *
     * @return $this
     * @throws LocalizedException
     */
    public function beforeSave()
    {
        $value = $this->getValue();
        if (is_array($value)) {
            foreach ($value as $rowId => $row) {
                if ($rowId === '__empty' || !is_array($row) || !$this->isFilledIn($row)) {
                    continue;
                }

                if ($this->getField($row, 'country_id') === '') {
                    throw new LocalizedException(
                        __(
                            'Store pickup location "%1" has no country. '
                            . 'Every location must have one, otherwise orders collected from it are placed '
                            . 'with an incomplete shipping address.',
                            $this->describe($row)
                        )
                    );
                }
            }
        }

        return parent::beforeSave();
    }

    /**
     * Whether the admin entered anything at all in this row.
     *
     * A row left entirely blank is dropped rather than rejected — the grid can
     * post one when a store is added and then cleared instead of deleted.
     *
     * @param array<string, mixed> $row
     * @return bool
     */
    private function isFilledIn(array $row): bool
    {
        foreach (self::ROW_FIELDS as $field) {
            if ($this->getField($row, $field) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * How to name the offending row in the error message.
     *
     * @param array<string, mixed> $row
     * @return string
     */
    private function describe(array $row): string
    {
        foreach (['name', 'code'] as $field) {
            $value = $this->getField($row, $field);
            if ($value !== '') {
                return $value;
            }
        }

        return (string) __('(unnamed)');
    }

    /**
     * @param array<string, mixed> $row
     * @param string $field
     * @return string
     */
    private function getField(array $row, string $field): string
    {
        return isset($row[$field]) && !is_array($row[$field]) ? trim((string) $row[$field]) : '';
    }
}
