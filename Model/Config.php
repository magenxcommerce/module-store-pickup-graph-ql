<?php
/**
 * Copyright © Magenx. All rights reserved.
 */
declare(strict_types=1);

namespace Magenx\StorePickupGraphQl\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed access to the In-Store Pickup carrier configuration
 * (Stores → Configuration → Sales → Shipping Methods → In-Store Pickup).
 */
class Config implements ResetAfterRequestInterface
{
    public const CARRIER_CODE = 'magenx_storepickup';

    private const XML_PATH_ACTIVE = 'carriers/magenx_storepickup/active';
    private const XML_PATH_TITLE = 'carriers/magenx_storepickup/title';
    private const XML_PATH_LOCATIONS = 'carriers/magenx_storepickup/locations';

    /** The columns an admin grid row may define, in output order. */
    private const FIELDS = [
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
     * Decoded locations, memoized per store for the lifetime of this request.
     *
     * @var array<string, array<int, array<string, mixed>>>
     */
    private array $locationsCache = [];

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param Json $serializer
     */
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Json $serializer
    ) {
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->locationsCache = [];
    }

    /**
     * Whether in-store pickup is enabled (the carrier is active).
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ACTIVE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * The shopper-facing carrier title.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getTitle(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_TITLE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    /**
     * The configured pickup locations, normalized to a stable, keyed shape.
     *
     * Rows without a `code` or `name` are skipped (both are required to attach
     * the location to a cart and to display it). Numeric latitude/longitude are
     * cast to float or nulled when blank/non-numeric.
     *
     * @param int|null $storeId
     * @return array<int, array<string, mixed>>
     */
    public function getLocations(?int $storeId = null): array
    {
        $cacheKey = (string) $storeId;
        if (isset($this->locationsCache[$cacheKey])) {
            return $this->locationsCache[$cacheKey];
        }

        $raw = (string) $this->scopeConfig->getValue(self::XML_PATH_LOCATIONS, ScopeInterface::SCOPE_STORE, $storeId);
        if ($raw === '') {
            return $this->locationsCache[$cacheKey] = [];
        }

        try {
            $decoded = $this->serializer->unserialize($raw);
        } catch (\InvalidArgumentException $e) {
            return $this->locationsCache[$cacheKey] = [];
        }

        if (!is_array($decoded)) {
            return $this->locationsCache[$cacheKey] = [];
        }

        $locations = [];
        foreach ($decoded as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = isset($row['code']) ? trim((string) $row['code']) : '';
            $name = isset($row['name']) ? trim((string) $row['name']) : '';
            if ($code === '' || $name === '') {
                continue;
            }

            $location = [];
            foreach (self::FIELDS as $field) {
                $location[$field] = isset($row[$field]) ? trim((string) $row[$field]) : '';
            }

            $location['latitude'] = $this->toFloatOrNull($location['latitude']);
            $location['longitude'] = $this->toFloatOrNull($location['longitude']);

            $locations[$code] = $location;
        }

        return $this->locationsCache[$cacheKey] = array_values($locations);
    }

    /**
     * Look up a single location by its code, or null when not configured.
     *
     * @param string $code
     * @param int|null $storeId
     * @return array<string, mixed>|null
     */
    public function getLocationByCode(string $code, ?int $storeId = null): ?array
    {
        if ($code === '') {
            return null;
        }
        foreach ($this->getLocations($storeId) as $location) {
            if (($location['code'] ?? null) === $code) {
                return $location;
            }
        }

        return null;
    }

    /**
     * @param string $value
     * @return float|null
     */
    private function toFloatOrNull(string $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
