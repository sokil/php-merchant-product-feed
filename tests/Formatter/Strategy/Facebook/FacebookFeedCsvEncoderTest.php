<?php

declare(strict_types=1);

namespace Sokil\Merchant\ProductFeed\Formatter\Strategy\Facebook;

use PHPUnit\Framework\TestCase;
use Sokil\Merchant\ProductFeed\Model\Feed;
use Sokil\Merchant\ProductFeed\Model\Product;
use Sokil\Merchant\ProductFeed\Model\ProductField\Availability;
use Sokil\Merchant\ProductFeed\Model\ProductField\Condition;
use Sokil\Merchant\ProductFeed\Model\ProductField\Price;
use Sokil\Merchant\ProductFeed\Model\ProductField\Url;

class FacebookFeedCsvEncoderTest extends TestCase
{
    public function testEncode()
    {
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

        $feed = new Feed([$product]);

        $encoder = new FacebookFeedCsvEncoder();
        $normaliser = new FacebookProductNormaliser();

        $actualOutput = iterator_to_array($encoder->encode($feed, $normaliser));

        $expectedOutput = [
            'id,title,description,availability,condition,price,link,image_link,brand',
            'sku,title,description,"in stock",new,"42.42 UAH",https://example.com/item,https://example.com/item.png,SomeBrand',
        ];

        $this->assertSame(
            $expectedOutput,
            $actualOutput
        );
    }
}