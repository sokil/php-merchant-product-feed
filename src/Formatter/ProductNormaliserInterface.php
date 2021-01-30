<?php

declare(strict_types=1);

namespace Sokil\Merchant\ProductFeed\Formatter\Strategy;

use Sokil\Merchant\ProductFeed\Model\Product;

interface ProductNormaliserInterface
{
    public function normalise(Product $product): array;

    public function isSupported(string $marketingPlatform): bool;
}