# php-merchant-product-feed

Builder of Google, Facebook and OpenAI product feeds

[![Test](https://github.com/sokil/php-merchant-product-feed/workflows/Test/badge.svg?branch=main)](https://github.com/sokil/php-merchant-product-feed/actions?query=workflow%3ATest)
[![Latest Stable Version](https://poser.pugx.org/sokil/php-merchant-product-feed/v/stable.png)](https://packagist.org/packages/sokil/php-merchant-product-feed)
[![Coverage Status](https://coveralls.io/repos/sokil/php-merchant-product-feed/badge.png?1)](https://coveralls.io/r/sokil/php-merchant-product-feed)
[![Total Downloads](http://img.shields.io/packagist/dt/sokil/php-merchant-product-feed.svg?1)](https://packagist.org/packages/sokil/php-merchant-product-feed)
[![Daily Downloads](https://poser.pugx.org/sokil/php-merchant-product-feed/d/daily)](https://packagist.org/packages/sokil/php-merchant-product-feed/stats)

## Facebook

### Useful links

* [Marketing API / Product catalog ](https://developers.facebook.com/docs/marketing-api/catalog)
* [Commence platform / Catalog and Inventory](https://developers.facebook.com/docs/commerce-platform/catalog)
* [Feed Update API](https://developers.facebook.com/docs/marketing-api/catalog/guides/feed-api)

## Google

### Useful links

* [Google Merchant Center / Catalog](https://support.google.com/merchants/answer/7052112?visit_id=637475497990766300-2364174748&hl=ru&rd=1)

### Useage

```php
<?php

// build set of products
$product = new Product(
    'sku',
    'title',
    'description',
    new Availability(Availability::IN_STOCK),
    new Condition(Condition::NEW),
    new Price('42.42', 'UAH'),
    new Url('https://example.com/item'),
    new Url('https://example.com/item.png'),
    'SomeBrand'
);

// build feed
$feed = new Feed([$product]);

// create formatter, devince product normaliser for martketing platform and define encoder to some formats
$formatter = new Formatter(
    [new FacebookProductNormaliser()],
    [new FacebookFeedCsvEncoder()]
);

// formatted feed is generator which yields parts of feed
$generator = $formatter->format($feed, 'facebook', 'csv');

// send feed to output
header('Content-type: text/csv');

foreach ($generator as $streamChunk) {
    echo $streamChunk;
}
        
```

## OpenAI (ChatGPT search and Ads)

### Useful links

* [Product feed spec](https://developers.openai.com/commerce/specs/feed)
* [Ads product feeds](https://developers.openai.com/ads/product-feeds)

### Usage

Products are built exactly as for the Google feed. The formatter writes a UTF-8 CSV with OpenAI's column names and converts the values to OpenAI's format (`in_stock`, `89.00 USD`, `true`).

```php
<?php

use Performing\FeedBuilder\Feed;
use Performing\FeedBuilder\Formatters\OpenAiFeedFormatter;
use Performing\FeedBuilder\Objects\Availability;
use Performing\FeedBuilder\Objects\Condition;
use Performing\FeedBuilder\Objects\Price;
use Performing\FeedBuilder\Objects\Product;
use Performing\FeedBuilder\Objects\Url;

$product = (new Product(
    id: 'SKU-001',
    title: 'Trail Running Shoe',
    description: 'Lightweight trail shoe for daily runs',
    availability: Availability::InStock,
    condition: Condition::New,
    price: new Price('89.00', 'USD'),
    link: new Url('https://example.com/products/sku-001'),
    imageLink: new Url('https://example.com/images/sku-001.jpg'),
    brand: 'Acme',
))->additional([
    'custom_label_0' => 'Running',                    // custom_label_* are grouped into ads_metadata
    'seller_url' => 'https://example.com',            // OpenAI column names become columns
    'return_policy' => 'https://example.com/returns',
]);

$formatter = new OpenAiFeedFormatter(
    sellerName: 'Acme',                               // seller_name
    targetCountries: ['US'],                          // target_countries
    storeCountry: 'US',                               // store_country
    onSkippedProduct: function (string $itemId, string $field, string $reason): void {
        error_log("Product {$itemId} left out of the OpenAI feed: {$field} {$reason}");
    },
);

$csv = $formatter->format(new Feed([$product]));

// or write it to a stream, e.g. the HTTP response, and get null back
$stream = fopen('php://output', 'w');
(new OpenAiFeedFormatter('Acme', ['US'], 'US', stream: $stream))->format(new Feed([$product]));
fclose($stream);
```

* With a stream, the CSV is built in a `php://temp` buffer (on disk above 2 MB) and copied in chunks, so large feeds do not need memory for the whole file.
* To publish a file, e.g. for SFTP, write to a unique temporary file in the same directory (`tempnam()`), and `rename()` it over the published file only after `format()` succeeds and the stream is closed, checking the result of `rename()`. Delete the temporary file when anything fails. This way a failed or concurrent export never publishes a partial or empty feed.
* Markets: OpenAI's standard upload currently targets the US. Use other countries in `targetCountries` and `storeCountry` only after OpenAI confirms them for your integration.
* Invalid products are left out of the feed and each problem is reported to `onSkippedProduct`, e.g. `('42', 'image_url', 'is empty')`. A product is invalid when a required text is empty, `url` or `image_url` is not a valid absolute HTTP(S) URL (OpenAI Ads requires public HTTPS URLs), the price is malformed, not greater than zero or has decimals its currency does not allow, a value is not UTF-8, or a custom field has the wrong type. If every product of a non-empty feed is invalid, `format()` throws and writes nothing. A write error, including a partial write, always throws.
* Every product gets `is_ads_eligible=true`. Pass `adsEligible: false` to change the default, or set `is_ads_eligible` with `additional()` on a single product. An empty custom field (`null` or `''`) keeps the default.
* `sale_price` is written only when it is greater than zero, lower than `price` in the same currency, and, when the product has a `salePriceEffectiveDate`, the feed is generated inside that period. OpenAI does not schedule sales, so regenerate the feed when a sale starts or ends.
* `target_countries` is written as a comma-separated list, e.g. `US` or `US,CA`.
* Custom fields supported as columns: `seller_name`, `is_ads_eligible`, `seller_url`, `return_policy`, `product_category`, `additional_image_urls`, `gtin`, `mpn`, `color`, `is_eligible_search`. Other keys are ignored. `is_ads_eligible` and `is_eligible_search` accept `true`, `false`, `'true'` or `'false'`. `additional_image_urls` accepts a list of URLs or a comma-separated string.
* Optional columns appear in the header only when at least one product has a value for them.
