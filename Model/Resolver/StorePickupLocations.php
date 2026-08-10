<?php
/**
 * Copyright © Magenx. All rights reserved.
 */
declare(strict_types=1);

namespace Magenx\StorePickupGraphQl\Model\Resolver;

use Magenx\StorePickupGraphQl\Model\Config;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

/**
 * Resolves the `storePickupLocations` query.
 *
 * Returns the admin-configured physical stores for the current store view.
 * Unauthenticated (guests can see pickup options too). Returns an empty list
 * when the feature is disabled so the storefront degrades gracefully.
 */
class StorePickupLocations implements ResolverInterface
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
        $storeId = (int) $context->getExtensionAttributes()->getStore()->getId();

        if (!$this->config->isEnabled($storeId)) {
            return [];
        }

        return $this->config->getLocations($storeId);
    }
}
