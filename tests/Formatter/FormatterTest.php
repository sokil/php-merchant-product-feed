<?php

declare(strict_types=1);

namespace Sokil\Merchant\ProductFeed\Formatter;

use PHPUnit\Framework\TestCase;
use Sokil\Merchant\ProductFeed\Model\Feed;
use Sokil\Merchant\ProductFeed\Model\Product;

class FormatterTest extends TestCase
{
    public function testFormat()
    {
        $normaliser = new class() implements ProductNormaliserInterface
        {
            public function normalise(Product $product): array
            {
                return [
                    'param' => 42,
                ];
            }

            public function isSupported(string $marketingPlatform): bool
            {
                return $marketingPlatform === 'platform';
            }

        };

        $encoder = new class() implements FeedEncoderInterface
        {
            public function encode(Feed $feed, ProductNormaliserInterface $productNormaliser): \Generator
            {
                foreach ($feed as $product) {
                    yield \json_encode($productNormaliser->normalise($product));
                }
            }

            public function isSupported(string $marketingPlatform, string $format): bool
            {
                return $marketingPlatform === 'platform' && $format === 'format';
            }

        };

        $formatter = new Formatter(
            [$normaliser],
            [$encoder]
        );

        $feed = new Feed([$this->createMock(Product::class)]);

        $generator = $formatter->format($feed, 'platform', 'format');

        $this->assertEquals(
            '{"param":42}',
            implode('', iterator_to_array($generator))
        );
    }
}