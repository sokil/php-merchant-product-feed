# php-merchant-product-feed

Builder of Facebook and Google product feeds

[![Test](https://github.com/sokil/php-merchant-product-feed/workflows/Test/badge.svg?branch=main)](https://github.com/sokil/php-merchant-product-feed/actions?query=workflow%3ATest)
[![Latest Stable Version](https://poser.pugx.org/sokil/php-merchant-product-feed/v/stable.png)](https://packagist.org/packages/sokil/php-merchant-product-feed)
[![Coverage Status](https://coveralls.io/repos/sokil/php-merchant-product-feed/badge.png?1)](https://coveralls.io/r/sokil/php-merchant-product-feed)
[![Total Downloads](http://img.shields.io/packagist/dt/sokil/php-merchant-product-feed.svg?1)](https://packagist.org/packages/sokil/php-merchant-product-feed)
[![Daily Downloads](https://poser.pugx.org/sokil/php-merchant-product-feed/d/daily)](https://packagist.org/packages/sokil/php-merchant-product-feed/stats)

## Facebook

### Useful links

* [Marketing API / Product catalog ](https://developers.facebook.com/docs/marketing-api/catalog)
* [Commence platform / Catalog and Inventory](https://developers.facebook.com/docs/commerce-platform/catalog)

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
