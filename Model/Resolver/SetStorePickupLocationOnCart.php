<?php
/**
 * Copyright © Magenx. All rights reserved.
 */
declare(strict_types=1);

namespace Magenx\StorePickupGraphQl\Model\Resolver;

use Magenx\StorePickupGraphQl\Model\Config;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;

/**
 * Resolves the `setStorePickupLocationOnCart` mutation.
 *
 * Persists (or clears) the chosen pickup-location code on the quote. The code
 * is validated against the configured locations; the human-readable address is
 * always resolved from config, so only the code is stored.
 */
class SetStorePickupLocationOnCart implements ResolverInterface
{
    /**
     * @param GetCartForUser $getCartForUser
     * @param CartRepositoryInterface $cartRepository
     * @param Config $config
     */
    public function __construct(
        private readonly GetCartForUser $getCartForUser,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly Config $config
    ) {
    }

    /**
     * @inheritDoc
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null)
    {
        if (empty($args['input']['cart_id'])) {
            throw new GraphQlInputException(__('Required parameter "cart_id" is missing.'));
        }

        $maskedCartId = (string) $args['input']['cart_id'];
        $code = isset($args['input']['location_code']) ? trim((string) $args['input']['location_code']) : '';

        $storeId = (int) $context->getExtensionAttributes()->getStore()->getId();
        $cart = $this->getCartForUser->execute($maskedCartId, $context->getUserId(), $storeId);

        if ($code !== '' && $this->config->getLocationByCode($code, $storeId) === null) {
            throw new GraphQlInputException(
                __('The pickup location "%1" is not available.', $code)
            );
        }

        $cart->setData('magenx_pickup_location_code', $code !== '' ? $code : null);

        try {
            $this->cartRepository->save($cart);
        } catch (NoSuchEntityException $e) {
            throw new GraphQlInputException(__('Could not save the pickup location on the cart.'), $e);
        }

        return ['cart' => ['model' => $cart]];
    }
}
