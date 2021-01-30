<?php

declare(strict_types=1);

namespace Sokil\Merchant\ProductFeed\Normaliser\Facebook;

use Sokil\Merchant\ProductFeed\Formatter\Strategy\ProductNormaliserInterface;
use Sokil\Merchant\ProductFeed\Model\Product;
use Sokil\Merchant\ProductFeed\Model\ProductField\Availability;

/**
 * @link https://developers.facebook.com/docs/commerce-platform/catalog/fields#additional-fields
 */
class FacebookProductNormaliser implements ProductNormaliserInterface
{
    private const AVAILABILITY_MAP = [
        Availability::IN_STOCK => 'in stock',
    ];

    public function normalise(Product $product): array
    {
        if (mb_strlen($product->getId()) > 100) {
            throw new \InvalidArgumentException('Product id must be less or equal 100 characters');
        }

        if (mb_strlen($product->getId()) > 150) {
            throw new \InvalidArgumentException('Product title must be less or equal 150 characters');
        }

        if (mb_strlen($product->getDescription()) > 5000) {
            throw new \InvalidArgumentException('Product title must be less or equal 5000 characters');
        }

        $availability = self::AVAILABILITY_MAP[$product->getAvailability()->getValue()] ?? null;
        if (empty($availability)) {

        }

        $normalisedProduct = [
            'id' => $product->getId(),
            'title' => $product->getTitle(),
            'description' => $product->getDescription(),
            'availability' => $availability,
        ];
    }

    public function isSupported(string $marketingPlatform): bool
    {
        return strtolower($marketingPlatform) === 'facebook';
    }


}
