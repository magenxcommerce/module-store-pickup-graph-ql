<?php
/**
 * Copyright © Magenx. All rights reserved.
 */
declare(strict_types=1);

namespace Magenx\StorePickupGraphQl\Model\Resolver\Order;

use Magenx\StorePickupGraphQl\Model\Config;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Resolves `CustomerOrder.store_pickup_location`.
 *
 * Reads the pickup-location code copied onto the order at placement and
 * hydrates it into the full configured address. Null for a shipped order.
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
        if (!isset($value['model']) || !$value['model'] instanceof OrderInterface) {
            throw new GraphQlInputException(__('"model" value should be specified.'));
        }

        /** @var OrderInterface $order */
        $order = $value['model'];
        $code = (string) $order->getData('magenx_pickup_location_code');
        if ($code === '') {
            return null;
        }

        return $this->config->getLocationByCode($code, (int) $order->getStoreId());
    }
}
