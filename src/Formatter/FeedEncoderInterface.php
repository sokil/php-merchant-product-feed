<?php

declare(strict_types=1);

namespace Sokil\Merchant\ProductFeed\Formatter;

use Sokil\Merchant\ProductFeed\Formatter\Strategy\ProductNormaliserInterface;
use Sokil\Merchant\ProductFeed\Model\Feed;

interface FeedEncoderInterface
{
    public function encode(Feed $feed, ProductNormaliserInterface $productNormaliser): string;

    public function isSupported(string $marketingPlatform, string $format): bool;
}