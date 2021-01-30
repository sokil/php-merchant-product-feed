<?php

declare(strict_types=1);

namespace Sokil\Merchant\ProductFeed\Formatter\Facebook\Strategy;

use Sokil\Merchant\ProductFeed\Formatter\FeedEncoderInterface;
use Sokil\Merchant\ProductFeed\Formatter\Strategy\ProductNormaliserInterface;
use Sokil\Merchant\ProductFeed\Model\Feed;

class FacebookFeedCsvEncoder implements FeedEncoderInterface
{
    public function encode(Feed $feed, ProductNormaliserInterface $productNormaliser): string
    {
        return '';
    }

    public function isSupported(string $marketingPlatform, string $format): bool
    {
        return strtolower($marketingPlatform) === 'facebook' && strtolower($format) === 'csv';
    }

}