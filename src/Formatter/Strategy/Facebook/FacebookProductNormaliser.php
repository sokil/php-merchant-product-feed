<?php

declare(strict_types=1);

namespace Sokil\Merchant\ProductFeed\Formatter\Strategy\Facebook;

use Sokil\Merchant\ProductFeed\Formatter\ProductNormaliserInterface;
use Sokil\Merchant\ProductFeed\Model\Product;
use Sokil\Merchant\ProductFeed\Model\ProductField\Availability;
use Sokil\Merchant\ProductFeed\Model\ProductField\Condition;

/**
 * @link https://developers.facebook.com/docs/commerce-platform/catalog/fields#additional-fields
 */
class FacebookProductNormaliser implements ProductNormaliserInterface
{
    private const AVAILABILITY_MAP = [
        Availability::IN_STOCK => 'in stock',
        Availability::AVAILABLE_FOR_ORDER => 'available for order',
        Availability::OUT_OF_STOCK => 'out of stock',
        Availability::DISCONTINUED => 'discontinued',
    ];

    private const CONDITION_MAP = [
        Condition::NEW => 'new',
        Condition::REFURBISHED => 'refurbished',
        Condition::USED => 'used',
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
            throw new \InvalidArgumentException('Unknown availability specified');
        }

        $condition = self::CONDITION_MAP[$product->getCondition()->getValue()] ?? null;
        if (empty($condition)) {
            throw new \InvalidArgumentException('Unknown condition specified');
        }

        $normalisedProduct = [
            'id' => $product->getId(),
            'title' => $product->getTitle(),
            'description' => $product->getDescription(),
            'availability' => $availability,
            'condition' => $condition,
            'price' => $product->getPrice()->getAmount() . ' ' . $product->getPrice()->getIso4210Currency(),
            'link' => $product->getLink()->getValue(),
            'image_link' => $product->getImageLink()->getValue(),
            'brand' => $product->getBrand(),
        ];

        return $normalisedProduct;
    }

    public function isSupported(string $marketingPlatform): bool
    {
        return strtolower($marketingPlatform) === 'facebook';
    }


}
