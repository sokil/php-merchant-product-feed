<?php

declare(strict_types=1);

namespace Sokil\Merchant\ProductFeed\Formatter\Strategy\Facebook;

use Sokil\Merchant\ProductFeed\Formatter\FeedEncoderInterface;
use Sokil\Merchant\ProductFeed\Formatter\ProductNormaliserInterface;
use Sokil\Merchant\ProductFeed\Model\Feed;

/**
 * Comma-separated value. Works with most spreadsheet programs. The first row specifies the column header. Subsequent
 * rows supply the corresponding values for each route.
 *
 * Fields containing whitespace or commas should be enclosed in "double quotes". A double quote inside a
 * double-quoted field must be escaped with two consecutive double quotes.
 *
 * Nested or multi-value fields, such as image, can be represented using JSON-encoded values or by a set of
 * "flattened" plain-text columns labeled using JSON-path syntax.
 *
 * You can reference our CSV (.csv) example files as you're creating your feed, but we recommend using Catalog Manager as your primary source.
 *
 * Example:
 * @link https://developers.facebook.com/docs/marketing-api/catalog/reference#example-csv-feed-commerce
 */
class FacebookFeedCsvEncoder implements FeedEncoderInterface
{
    public function encode(Feed $feed, ProductNormaliserInterface $productNormaliser): \Generator
    {
        // write header
        $header = array_keys($productNormaliser->normalise($feed->current()));
        yield $this->arrayToCsv($header);

        // write body
        foreach ($feed as $product) {
            yield $this->arrayToCsv($productNormaliser->normalise($product));
        }
    }

    public function isSupported(string $marketingPlatform, string $format): bool
    {
        return strtolower($marketingPlatform) === 'facebook' && strtolower($format) === 'csv';
    }

    private function arrayToCsv(array $row): string
    {
        $csvFile = fopen('php://memory', 'w+');
        fputcsv($csvFile, $row, ',', '"');
        rewind($csvFile);
        return trim(stream_get_contents($csvFile));
    }

}