# php-merchant-product-feed
Builder of Facebook and Google product feeds

## Facebook

### Useful links

* [Marketing API / Product catalog ](https://developers.facebook.com/docs/marketing-api/catalog)
* [Commence platform / Catalog and Inventory](https://developers.facebook.com/docs/commerce-platform/catalog)

## Google

### Useful links

* [Goole Merchant Center / Catalog](https://support.google.com/merchants/answer/7052112?visit_id=637475497990766300-2364174748&hl=ru&rd=1)

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
