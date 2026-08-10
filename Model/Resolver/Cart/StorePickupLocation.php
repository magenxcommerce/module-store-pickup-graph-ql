<?php
/**
 * Copyright © Magenx. All rights reserved.
 */
declare(strict_types=1);

namespace Magenx\StorePickupGraphQl\Model\Resolver\Cart;

use Magenx\StorePickupGraphQl\Model\Config;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Model\Quote;

/**
 * Resolves `Cart.store_pickup_location`.
 *
 * Reads the pickup-location code persisted on the quote and hydrates it into
 * the full configured address. Null when no pickup location is set.
 */
class StorePickupLocation implements ResolverInterface
{
    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        if (!isset($value['model']) || !$value['model'] instanceof Quote) {
            throw new GraphQlInputException(__('"model" value should be specified.'));
        }

        /** @var Quote $quote */
        $quote = $value['model'];
        $code = (string) $quote->getData('magenx_pickup_location_code');
        if ($code === '') {
            return null;
        }

        return $this->config->getLocationByCode($code, (int) $quote->getStoreId());
    }
}
