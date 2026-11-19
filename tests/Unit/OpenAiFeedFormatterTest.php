<?php

declare(strict_types=1);

use Performing\FeedBuilder\Feed;
use Performing\FeedBuilder\Formatters\OpenAiFeedFormatter;
use Performing\FeedBuilder\Objects\Availability;
use Performing\FeedBuilder\Objects\Condition;
use Performing\FeedBuilder\Objects\DateTimeRange;
use Performing\FeedBuilder\Objects\Price;
use Performing\FeedBuilder\Objects\Product;
use Performing\FeedBuilder\Objects\Url;

/**
 * Parse the generated CSV into rows keyed by column name, reading quoted multiline cells too
 *
 * @return array{0: string[], 1: array<int, array<string, string>>}
 */
function openAiCsv(string $csv): array
{
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, $csv);
    rewind($stream);

    $header = fgetcsv($stream, null, ',', '"', '');
    $rows = [];

    while (($values = fgetcsv($stream, null, ',', '"', '')) !== false) {
        $rows[] = array_combine($header, $values);
    }

    fclose($stream);

    return [$header, $rows];
}

function openAiProduct(
    string $id = 'test-product-1',
    ?Price $salePrice = null,
    Availability $availability = Availability::InStock,
    string $price = '19.99',
    string $currency = 'EUR',
    string $title = 'Test Product',
    string $description = 'This is a test product description',
    string $url = 'https://example.com/product',
    string $imageUrl = 'https://example.com/product.jpg',
    ?DateTimeRange $salePeriod = null,
): Product {
    return new Product(
        $id,
        $title,
        $description,
        $availability,
        Condition::Used,
        new Price($price, $currency),
        new Url($url),
        new Url($imageUrl),
        'Test Brand',
        $salePrice,
        salePriceEffectiveDate: $salePeriod,
    );
}

function openAiFormatter(mixed $stream = null, ?Closure $onSkippedProduct = null): OpenAiFeedFormatter
{
    return new OpenAiFeedFormatter(
        sellerName: 'Test Seller',
        targetCountries: ['IT'],
        storeCountry: 'IT',
        stream: $stream,
        onSkippedProduct: $onSkippedProduct,
    );
}

test('OpenAiFeedFormatter writes the required columns', function () {
    [$header, $rows] = openAiCsv(openAiFormatter()->format(new Feed([openAiProduct()])));

    expect($header)->toBe([
        'item_id',
        'title',
        'description',
        'url',
        'brand',
        'seller_name',
        'image_url',
        'availability',
        'price',
        'target_countries',
        'store_country',
        'condition',
        'is_ads_eligible',
    ]);

    expect($rows)->toHaveCount(1);
    expect($rows[0])->toBe([
        'item_id' => 'test-product-1',
        'title' => 'Test Product',
        'description' => 'This is a test product description',
        'url' => 'https://example.com/product',
        'brand' => 'Test Brand',
        'seller_name' => 'Test Seller',
        'image_url' => 'https://example.com/product.jpg',
        'availability' => 'in_stock',
        'price' => '19.99 EUR',
        'target_countries' => 'IT',
        'store_country' => 'IT',
        'condition' => 'used',
        'is_ads_eligible' => 'true',
    ]);
});

test('OpenAiFeedFormatter writes only the header for an empty feed', function () {
    [$header, $rows] = openAiCsv(openAiFormatter()->format(new Feed([])));

    expect($header)->toContain('item_id');
    expect($rows)->toBe([]);
});

test('OpenAiFeedFormatter writes target countries as a comma-separated list', function () {
    $formatter = new OpenAiFeedFormatter('Test Seller', [3 => 'US', 7 => 'CA'], 'US');

    [, $rows] = openAiCsv($formatter->format(new Feed([openAiProduct()])));

    expect($rows[0]['target_countries'])->toBe('US,CA');
});

test('OpenAiFeedFormatter maps availability to OpenAI values', function () {
    [, $rows] = openAiCsv(openAiFormatter()->format(new Feed([
        openAiProduct('in'),
        openAiProduct('out', availability: Availability::OutOfStock),
    ])));

    expect($rows[0]['availability'])->toBe('in_stock');
    expect($rows[1]['availability'])->toBe('out_of_stock');
});

test('OpenAiFeedFormatter adds decimals to whole amounts', function () {
    [, $rows] = openAiCsv(openAiFormatter()->format(new Feed([openAiProduct(price: '12500')])));

    expect($rows[0]['price'])->toBe('12500.00 EUR');
});

test('OpenAiFeedFormatter writes whole amounts for currencies without decimals', function () {
    $skipped = [];

    $csv = openAiFormatter(onSkippedProduct: function (string $itemId, string $field, string $reason) use (&$skipped): void {
        $skipped[] = "{$itemId} {$field} {$reason}";
    })->format(new Feed([
        openAiProduct('whole', price: '1500', currency: 'JPY'),
        openAiProduct('zero-decimals', price: '1500.00', currency: 'JPY'),
        openAiProduct('decimals', price: '1500.25', currency: 'JPY'),
    ]));

    [, $rows] = openAiCsv($csv);

    expect(array_column($rows, 'price'))->toBe(['1500 JPY', '1500 JPY']);
    expect($skipped)->toBe(['decimals price has more decimals than JPY allows']);
});

test('OpenAiFeedFormatter includes the sale price only when positive, lower than the price and in the same currency', function () {
    [$header, $rows] = openAiCsv(openAiFormatter()->format(new Feed([
        openAiProduct('lower', salePrice: new Price('15.99', 'EUR')),
        openAiProduct('equal', salePrice: new Price('19.99', 'EUR')),
        openAiProduct('higher', salePrice: new Price('29.99', 'EUR')),
        openAiProduct('zero', salePrice: new Price('0.00', 'EUR')),
        openAiProduct('other-currency', salePrice: new Price('9.99', 'USD')),
        openAiProduct('one-cent-less', salePrice: new Price('19.98', 'EUR')),
        openAiProduct('beyond-int', salePrice: new Price('99999999999999999999.98', 'EUR'), price: '99999999999999999999.99'),
        openAiProduct('malformed', salePrice: new Price('15.99', 'EUR extra')),
    ])));

    expect($header)->toContain('sale_price');
    expect(array_column($rows, 'sale_price', 'item_id'))->toBe([
        'lower' => '15.99 EUR',
        'equal' => '',
        'higher' => '',
        'zero' => '',
        'other-currency' => '',
        'one-cent-less' => '19.98 EUR',
        'beyond-int' => '99999999999999999999.98 EUR',
        'malformed' => '',
    ]);
});

test('OpenAiFeedFormatter includes the sale price only inside the sale period', function () {
    $now = new DateTimeImmutable();
    $sale = new Price('15.99', 'EUR');

    [, $rows] = openAiCsv(openAiFormatter()->format(new Feed([
        openAiProduct('expired', salePrice: $sale, salePeriod: new DateTimeRange(new DateTimeImmutable('2000-01-01'), new DateTimeImmutable('2000-01-31'))),
        openAiProduct('future', salePrice: $sale, salePeriod: new DateTimeRange(new DateTimeImmutable('2999-01-01'), new DateTimeImmutable('2999-01-31'))),
        openAiProduct('current', salePrice: $sale, salePeriod: new DateTimeRange($now->modify('-1 day'), $now->modify('+1 day'))),
        openAiProduct('no-period', salePrice: $sale),
    ])));

    expect(array_column($rows, 'sale_price', 'item_id'))->toBe([
        'expired' => '',
        'future' => '',
        'current' => '15.99 EUR',
        'no-period' => '15.99 EUR',
    ]);
});

test('OpenAiFeedFormatter leaves out optional columns no product uses', function () {
    [$header] = openAiCsv(openAiFormatter()->format(new Feed([openAiProduct()])));

    expect($header)->not->toContain('sale_price');
    expect($header)->not->toContain('ads_metadata');
    expect($header)->not->toContain('seller_url');
});

test('OpenAiFeedFormatter groups custom labels into ads_metadata', function () {
    $product = openAiProduct()->additional([
        'custom_label_0' => 'Milano',
        'custom_label_1' => 2021,
        'custom_label_2' => '0',
        'custom_label_3' => '',
        'custom_label_4' => null,
    ]);

    [, $rows] = openAiCsv(openAiFormatter()->format(new Feed([$product])));

    expect(json_decode($rows[0]['ads_metadata'], true))->toBe([
        'custom_label_0' => 'Milano',
        'custom_label_1' => '2021',
        'custom_label_2' => '0',
    ]);
});

test('OpenAiFeedFormatter maps known custom fields to columns and ignores the others', function () {
    $product = openAiProduct()->additional([
        'seller_url' => 'https://example.com',
        'product_category' => 'Vehicles & Parts > Vehicles',
        'additional_image_urls' => ['https://example.com/1.jpg', 'https://example.com/2.jpg?size=10,20'],
        'is_eligible_search' => 'false',
        'unknown_field' => 'ignored',
    ]);

    [$header, $rows] = openAiCsv(openAiFormatter()->format(new Feed([$product])));

    expect($rows[0]['seller_url'])->toBe('https://example.com');
    expect($rows[0]['product_category'])->toBe('Vehicles & Parts > Vehicles');
    expect($rows[0]['additional_image_urls'])->toBe('https://example.com/1.jpg,https://example.com/2.jpg?size=10%2C20');
    expect($rows[0]['is_eligible_search'])->toBe('false');
    expect($header)->not->toContain('unknown_field');
});

test('OpenAiFeedFormatter lets a product override the feed defaults', function () {
    $product = openAiProduct()->additional([
        'seller_name' => 'Other Seller',
        'is_ads_eligible' => false,
    ]);

    [, $rows] = openAiCsv(openAiFormatter()->format(new Feed([$product])));

    expect($rows[0]['seller_name'])->toBe('Other Seller');
    expect($rows[0]['is_ads_eligible'])->toBe('false');
});

test('OpenAiFeedFormatter keeps the feed defaults when an override is empty', function () {
    $product = openAiProduct()->additional([
        'seller_name' => '',
        'is_ads_eligible' => null,
    ]);

    [, $rows] = openAiCsv(openAiFormatter()->format(new Feed([$product])));

    expect($rows[0]['seller_name'])->toBe('Test Seller');
    expect($rows[0]['is_ads_eligible'])->toBe('true');
});

test('OpenAiFeedFormatter quotes values with commas, quotes and newlines', function () {
    [, $rows] = openAiCsv(openAiFormatter()->format(new Feed([
        openAiProduct('quoted', title: 'Title, with "quotes"', description: "Line one\nLine two"),
        openAiProduct('next'),
    ])));

    expect($rows)->toHaveCount(2);
    expect($rows[0]['title'])->toBe('Title, with "quotes"');
    expect($rows[0]['description'])->toBe("Line one\nLine two");
    expect($rows[1]['item_id'])->toBe('next');
});

test('OpenAiFeedFormatter skips invalid products and reports why', function () {
    $skipped = [];

    $formatter = openAiFormatter(onSkippedProduct: function (string $itemId, string $field, string $reason) use (&$skipped): void {
        $skipped[] = "{$itemId} {$field} {$reason}";
    });

    $csv = $formatter->format(new Feed([
        openAiProduct('no-image', imageUrl: ''),
        openAiProduct('relative-url', url: '/product'),
        openAiProduct('space-in-host', url: 'https://bad host/product'),
        openAiProduct('no-description', description: '  '),
        openAiProduct('free', price: '0.00'),
        openAiProduct('currency-suffix', currency: 'EUR extra'),
        openAiProduct('amount-newline', price: "19.99\n"),
        openAiProduct('latin1', description: "Moto d'epoca \xE8 bella"),
        openAiProduct('array-label')->additional(['custom_label_0' => ['Milano']]),
        openAiProduct('numeric-flag')->additional(['is_ads_eligible' => 0]),
        openAiProduct('object-field')->additional(['color' => new stdClass()]),
        openAiProduct('accented-url', url: 'https://example.com/moto/città'),
        openAiProduct('valid'),
    ]));

    [, $rows] = openAiCsv($csv);

    expect(array_column($rows, 'item_id'))->toBe(['accented-url', 'valid']);
    expect($skipped)->toBe([
        'no-image image_url is empty',
        'relative-url url is not a valid absolute http or https URL',
        'space-in-host url is not a valid absolute http or https URL',
        'no-description description is empty',
        'free price is not greater than zero',
        'currency-suffix price is not an amount with a three-letter currency',
        'amount-newline price is not an amount with a three-letter currency',
        'latin1 description is not valid UTF-8',
        'array-label custom_label_0 is not a string or a number',
        'numeric-flag is_ads_eligible is not true or false',
        'object-field color is not a string or a number',
    ]);
});

test('OpenAiFeedFormatter writes nothing when every product is invalid', function () {
    $stream = fopen('php://temp', 'w+');

    expect(fn() => openAiFormatter($stream)->format(new Feed([openAiProduct(imageUrl: '')])))
        ->toThrow(RuntimeException::class, 'All 1 products are invalid, the feed was not written');

    rewind($stream);
    expect(stream_get_contents($stream))->toBe('');
});

test('OpenAiFeedFormatter writes to a stream and returns null', function () {
    $stream = fopen('php://temp', 'w+');

    $result = openAiFormatter($stream)->format(new Feed([openAiProduct('first'), openAiProduct('second')]));

    rewind($stream);
    [, $rows] = openAiCsv(stream_get_contents($stream));

    expect($result)->toBeNull();
    expect(array_column($rows, 'item_id'))->toBe(['first', 'second']);
});

test('OpenAiFeedFormatter writes a feed larger than one chunk to a stream unchanged', function () {
    $products = array_map(
        static fn(int $i): Product => openAiProduct("item-{$i}", description: str_repeat("Description of item {$i}. ", 50)),
        range(1, 100),
    );

    $stream = fopen('php://temp', 'w+');
    openAiFormatter($stream)->format(new Feed($products));
    rewind($stream);
    $written = stream_get_contents($stream);

    expect(strlen($written))->toBeGreaterThan(8192 * 10);
    expect($written)->toBe(openAiFormatter()->format(new Feed($products)));
});

test('OpenAiFeedFormatter throws when the stream is not writable', function () {
    $path = tempnam(sys_get_temp_dir(), 'openai-feed');
    $stream = fopen($path, 'r');

    // The formatter suppresses the write notice, but the test printer would still list it
    set_error_handler(static fn(): bool => true);

    try {
        expect(fn() => openAiFormatter($stream)->format(new Feed([openAiProduct()])))
            ->toThrow(RuntimeException::class, 'Could not write the feed: ');
    } finally {
        restore_error_handler();
        fclose($stream);
        unlink($path);
    }
});

/**
 * Stream that accepts the first 10 bytes and then no more, like a full disk
 */
final class OpenAiPartialWriteStream
{
    public mixed $context;

    private int $written = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_write(string $data): int
    {
        $accepted = max(0, min(strlen($data), 10 - $this->written));
        $this->written += $accepted;

        return $accepted;
    }

    public function stream_flush(): bool
    {
        return true;
    }
}

test('OpenAiFeedFormatter throws when the stream accepts only part of the feed', function () {
    if (!in_array('openai-partial', stream_get_wrappers(), true)) {
        stream_wrapper_register('openai-partial', OpenAiPartialWriteStream::class);
    }

    $stream = fopen('openai-partial://feed', 'w');

    expect(fn() => openAiFormatter($stream)->format(new Feed([openAiProduct()])))
        ->toThrow(RuntimeException::class, 'Could not write the feed: the stream accepts no more data');
});

test('OpenAiFeedFormatter throws before writing to the stream when the temporary buffer fails', function () {
    // Above 2 MB php://temp moves to a file in sys_temp_dir, which can be set only when PHP starts,
    // so a feed of about 3 MB is exported in a separate process whose temporary directory does not exist
    $code = str_replace('AUTOLOAD', var_export(dirname(__DIR__, 2) . '/vendor/autoload.php', true), <<<'PHP'
        require AUTOLOAD;

        use Performing\FeedBuilder\Feed;
        use Performing\FeedBuilder\Formatters\OpenAiFeedFormatter;
        use Performing\FeedBuilder\Objects\Availability;
        use Performing\FeedBuilder\Objects\Condition;
        use Performing\FeedBuilder\Objects\Price;
        use Performing\FeedBuilder\Objects\Product;
        use Performing\FeedBuilder\Objects\Url;

        $description = str_repeat('Description of the item. ', 200);
        $products = [];

        for ($i = 0; $i < 600; $i++) {
            $products[] = new Product(
                "item-{$i}",
                'Title',
                $description,
                Availability::InStock,
                Condition::Used,
                new Price('10.00', 'EUR'),
                new Url("https://example.com/{$i}"),
                new Url("https://example.com/{$i}.jpg"),
                'Brand',
            );
        }

        $stream = fopen('php://memory', 'w+');

        try {
            (new OpenAiFeedFormatter('Seller', ['IT'], 'IT', stream: $stream))->format(new Feed($products));
            echo 'no exception';
        } catch (RuntimeException $exception) {
            echo $exception->getMessage(), ' | bytes on the stream: ', ftell($stream);
        }
        PHP);

    $missing = sys_get_temp_dir() . '/openai-feed-missing-' . bin2hex(random_bytes(4));
    $command = implode(' ', [
        escapeshellarg(PHP_BINARY),
        '-d', escapeshellarg("sys_temp_dir={$missing}"),
        '-d', 'display_errors=0',
        '-d', 'log_errors=0',
        '-r', escapeshellarg($code),
    ]);

    exec($command, $output, $exitCode);
    $result = implode("\n", $output);

    expect($exitCode)->toBe(0);
    expect($result)->toStartWith('Could not write the feed: ');
    expect($result)->toEndWith(' | bytes on the stream: 0');
});

test('OpenAiFeedFormatter validates its settings', function () {
    expect(fn() => new OpenAiFeedFormatter(' ', ['IT'], 'IT'))
        ->toThrow(InvalidArgumentException::class, 'Seller name must be a non-empty UTF-8 string');

    expect(fn() => new OpenAiFeedFormatter('Test Seller', [], 'IT'))
        ->toThrow(InvalidArgumentException::class, 'At least one target country is required');

    expect(fn() => new OpenAiFeedFormatter('Test Seller', ['ITA'], 'IT'))
        ->toThrow(InvalidArgumentException::class, 'Countries must be alpha 2 by ISO 3166-1');

    expect(fn() => new OpenAiFeedFormatter('Test Seller', ["US\n"], 'US'))
        ->toThrow(InvalidArgumentException::class, 'Countries must be alpha 2 by ISO 3166-1');

    expect(fn() => new OpenAiFeedFormatter('Test Seller', ['IT'], 'it'))
        ->toThrow(InvalidArgumentException::class, 'Countries must be alpha 2 by ISO 3166-1');
});
