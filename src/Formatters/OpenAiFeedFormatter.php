<?php

declare(strict_types=1);

namespace Performing\FeedBuilder\Formatters;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use Performing\FeedBuilder\Feed;
use Performing\FeedBuilder\Objects\Availability;
use Performing\FeedBuilder\Objects\Price;
use Performing\FeedBuilder\Objects\Product;
use RuntimeException;
use Stringable;

/**
 * Formats a feed into an OpenAI product feed CSV file, used by ChatGPT search and ChatGPT Ads
 *
 * Products are built exactly as for the Google feed. The values OpenAI requires for the whole
 * feed (seller name, target countries, store country) are passed to the constructor.
 *
 * Custom fields set with Product::additional() are mapped as follows:
 * - custom_label_0 ... custom_label_N are grouped into the ads_metadata JSON object
 * - keys listed in CUSTOM_FIELD_COLUMNS become columns with the same name
 * - any other key is ignored
 * - an empty value (null or '') means "no value" and keeps the feed default
 *
 * A product with an invalid value (an empty required field, a malformed or relative URL, a malformed or
 * non-positive price, text that is not UTF-8, a custom field of the wrong type) is not written:
 * each problem is reported to the onSkippedProduct callback and the rest of the feed is written.
 * When every product of a non-empty feed is skipped, nothing is written and an exception is thrown.
 *
 * @link https://developers.openai.com/commerce/specs/feed
 * @link https://developers.openai.com/ads/product-feeds
 */
class OpenAiFeedFormatter implements FormatterInterface
{
    /**
     * Columns written for every product
     */
    private const BASE_COLUMNS = [
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
    ];

    /**
     * Columns written only when at least one product has a value for them
     */
    private const OPTIONAL_COLUMNS = [
        'sale_price',
        'ads_metadata',
        'seller_url',
        'return_policy',
        'product_category',
        'additional_image_urls',
        'gtin',
        'mpn',
        'color',
        'is_eligible_search',
    ];

    /**
     * Custom field keys copied into the column with the same name
     */
    private const CUSTOM_FIELD_COLUMNS = [
        'seller_name',
        'is_ads_eligible',
        'seller_url',
        'return_policy',
        'product_category',
        'additional_image_urls',
        'gtin',
        'mpn',
        'color',
        'is_eligible_search',
    ];

    /**
     * Columns that accept only true or false
     */
    private const BOOLEAN_COLUMNS = ['is_ads_eligible', 'is_eligible_search'];

    /**
     * Text columns that must have a value in every row
     */
    private const REQUIRED_COLUMNS = ['item_id', 'title', 'description', 'url', 'brand', 'seller_name', 'image_url'];

    /**
     * Required columns that must hold an absolute HTTP or HTTPS URL
     */
    private const URL_COLUMNS = ['url', 'image_url'];

    /**
     * ISO 4217 currencies without minor units, whose amounts must not have decimals
     */
    private const ZERO_DECIMAL_CURRENCIES = ['BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'];

    /**
     * @var string[]
     */
    private array $targetCountries;

    /**
     * @param string $sellerName Seller name shown for every product (seller_name)
     * @param string[] $targetCountries Countries where products are advertised, alpha 2 by ISO 3166-1 (target_countries)
     * @param string $storeCountry Country of the seller's store, alpha 2 by ISO 3166-1 (store_country)
     * @param bool $adsEligible Default value of is_ads_eligible, can be overridden per product with a custom field
     * @param resource|null $stream Stream to write the CSV to. When null, format() returns the CSV as a string
     * @param (Closure(string $itemId, string $field, string $reason): void)|null $onSkippedProduct Called once for each
     *     problem of a skipped product, e.g. ('42', 'image_url', 'is empty'), to log it
     */
    public function __construct(
        private string $sellerName,
        array $targetCountries,
        private string $storeCountry,
        private bool $adsEligible = true,
        private mixed $stream = null,
        private ?Closure $onSkippedProduct = null,
    ) {
        if (trim($sellerName) === '' || !$this->isUtf8($sellerName)) {
            throw new InvalidArgumentException('Seller name must be a non-empty UTF-8 string');
        }

        if ($targetCountries === []) {
            throw new InvalidArgumentException('At least one target country is required');
        }

        foreach ([...$targetCountries, $storeCountry] as $country) {
            if (!is_string($country) || preg_match('/^[A-Z]{2}\z/', $country) !== 1) {
                throw new InvalidArgumentException('Countries must be alpha 2 by ISO 3166-1');
            }
        }

        if ($stream !== null && !is_resource($stream)) {
            throw new InvalidArgumentException('Invalid stream resource');
        }

        $this->targetCountries = array_values($targetCountries);
    }

    /**
     * Format the feed as CSV and return it, or write it to the stream and return null
     *
     * @throws RuntimeException When every product is skipped or the CSV cannot be written
     */
    public function format(Feed $feed): ?string
    {
        $now = new DateTimeImmutable();
        $rows = [];

        foreach ($feed as $product) {
            [$row, $errors] = $this->toRow($product, $now);

            if ($errors === []) {
                $rows[] = $row;
                continue;
            }

            foreach ($errors as $field => $reason) {
                $this->onSkippedProduct?->__invoke($product->getId(), $field, $reason);
            }
        }

        if ($rows === [] && count($feed) > 0) {
            throw new RuntimeException(sprintf('All %d products are invalid, the feed was not written', count($feed)));
        }

        $columns = self::BASE_COLUMNS;

        foreach (self::OPTIONAL_COLUMNS as $column) {
            foreach ($rows as $row) {
                if (isset($row[$column])) {
                    $columns[] = $column;
                    break;
                }
            }
        }

        // The CSV is built in a temporary buffer, kept on disk once it is larger than 2 MB, then copied to the
        // stream in chunks. Every write, to the buffer too, is checked byte by byte, so a failed write always throws
        // and a failure while building the buffer throws before anything reaches the stream
        $buffer = fopen('php://temp', 'w+');

        if ($buffer === false) {
            throw new RuntimeException('Could not open temporary stream');
        }

        try {
            $this->write($buffer, $this->csvLine($columns));

            foreach ($rows as $row) {
                $this->write($buffer, $this->csvLine(array_map(static fn(string $column): string => $row[$column] ?? '', $columns)));
            }

            rewind($buffer);

            if ($this->stream === null) {
                $csv = stream_get_contents($buffer);

                if ($csv === false) {
                    throw new RuntimeException('Could not read the generated feed');
                }

                return $csv;
            }

            $this->copy($buffer, $this->stream);

            return null;
        } finally {
            fclose($buffer);
        }
    }

    /**
     * Map a product to its CSV values keyed by column, leaving out empty values, and collect its problems
     *
     * @return array{0: array<string, string>, 1: array<string, string>} Values, and reasons keyed by field
     */
    private function toRow(Product $product, DateTimeImmutable $now): array
    {
        $errors = [];

        $row = [
            'item_id' => $product->getId(),
            'title' => $product->getTitle(),
            'description' => $product->getDescription(),
            'url' => $product->getLink()->toString(),
            'brand' => $product->getBrand(),
            'seller_name' => $this->sellerName,
            'image_url' => $product->getImageLink()->toString(),
            'availability' => $this->formatAvailability($product->getAvailability()),
            'target_countries' => implode(',', $this->targetCountries),
            'store_country' => $this->storeCountry,
            'condition' => $product->getCondition()->toString(),
            'is_ads_eligible' => $this->adsEligible ? 'true' : 'false',
        ];

        try {
            $row['price'] = $this->formatPrice($product->getPrice());
        } catch (InvalidArgumentException $exception) {
            $errors['price'] = $exception->getMessage();
        }

        $salePrice = $this->formatSalePrice($product, $now);

        if ($salePrice !== null) {
            $row['sale_price'] = $salePrice;
        }

        $adsMetadata = [];

        foreach ($product->getCustomFields() as $key => $value) {
            $key = (string) $key;

            if ($value === null || $value === '') {
                continue;
            }

            if (preg_match('/^custom_label_\d+$/', $key) === 1) {
                if (!is_string($value) && !is_int($value) && !is_float($value)) {
                    $errors[$key] = 'is not a string or a number';
                } elseif (!$this->isUtf8((string) $value)) {
                    $errors[$key] = 'is not valid UTF-8';
                } else {
                    $adsMetadata[$key] = (string) $value;
                }

                continue;
            }

            if (!in_array($key, self::CUSTOM_FIELD_COLUMNS, true)) {
                continue;
            }

            try {
                $row[$key] = $this->formatCustomField($key, $value);
            } catch (InvalidArgumentException $exception) {
                $errors[$key] = $exception->getMessage();
            }
        }

        if ($adsMetadata !== []) {
            $row['ads_metadata'] = json_encode($adsMetadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }

        foreach (self::REQUIRED_COLUMNS as $column) {
            if (trim($row[$column]) === '') {
                $errors[$column] = 'is empty';
            }
        }

        foreach (self::URL_COLUMNS as $column) {
            if (!isset($errors[$column]) && !$this->isAbsoluteUrl($row[$column])) {
                $errors[$column] = 'is not a valid absolute http or https URL';
            }
        }

        foreach ($row as $column => $value) {
            if (!isset($errors[$column]) && !$this->isUtf8($value)) {
                $errors[$column] = 'is not valid UTF-8';
            }
        }

        return [array_filter($row, static fn(string $value): bool => $value !== ''), $errors];
    }

    /**
     * @throws InvalidArgumentException With the reason, when the value does not fit the column
     */
    private function formatCustomField(string $column, mixed $value): string
    {
        if (in_array($column, self::BOOLEAN_COLUMNS, true)) {
            return match ($value) {
                true, 'true' => 'true',
                false, 'false' => 'false',
                default => throw new InvalidArgumentException('is not true or false'),
            };
        }

        if ($column === 'additional_image_urls' && is_array($value)) {
            foreach ($value as $url) {
                if (!is_string($url)) {
                    throw new InvalidArgumentException('is not a string or a list of strings');
                }
            }

            // Commas separate the URLs, so a comma inside a URL must be percent-encoded
            return implode(',', array_map(static fn(string $url): string => str_replace(',', '%2C', $url), $value));
        }

        if (is_string($value) || is_int($value) || is_float($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        throw new InvalidArgumentException('is not a string or a number');
    }

    private function formatAvailability(Availability $availability): string
    {
        return match ($availability) {
            Availability::InStock => 'in_stock',
            Availability::OutOfStock => 'out_of_stock',
        };
    }

    /**
     * OpenAI expects the amount in major units with the decimals of the currency, e.g. "12500.00 EUR" or "1500 JPY"
     *
     * @throws InvalidArgumentException With the reason, when the price cannot be written
     */
    private function formatPrice(Price $price): string
    {
        $amount = $price->getAmount();
        $currency = $price->getIso4210Currency();

        // Price checks its values loosely ("19.99\n" and "USD extra" pass), so the full syntax is checked here
        if (preg_match('/^\d+(\.\d{2})?\z/', $amount) !== 1 || preg_match('/^[A-Z]{3}\z/', $currency) !== 1) {
            throw new InvalidArgumentException('is not an amount with a three-letter currency');
        }

        if ($this->significantDigits($amount) === '') {
            throw new InvalidArgumentException('is not greater than zero');
        }

        if (in_array($currency, self::ZERO_DECIMAL_CURRENCIES, true)) {
            if (str_ends_with($amount, '.00')) {
                $amount = substr($amount, 0, -3);
            } elseif (str_contains($amount, '.')) {
                throw new InvalidArgumentException("has more decimals than {$currency} allows");
            }
        } elseif (!str_contains($amount, '.')) {
            $amount .= '.00';
        }

        return "{$amount} {$currency}";
    }

    /**
     * OpenAI uses sale_price as it is, so it is written only while it applies: a valid price greater than zero,
     * lower than the price in the same currency, and inside the sale period when the product has one
     */
    private function formatSalePrice(Product $product, DateTimeImmutable $now): ?string
    {
        $salePrice = $product->getSalePrice();
        $price = $product->getPrice();
        $period = $product->getSalePriceEffectiveDate();

        if (
            $salePrice === null
            || $salePrice->getIso4210Currency() !== $price->getIso4210Currency()
            || ($period !== null && ($now < $period->getDateTimeFrom() || $now > $period->getDateTimeTo()))
        ) {
            return null;
        }

        try {
            $formatted = $this->formatPrice($salePrice);
        } catch (InvalidArgumentException) {
            return null;
        }

        return $this->isLower($salePrice->getAmount(), $price->getAmount()) ? $formatted : null;
    }

    /**
     * Compare two amounts digit by digit, so prices of any size are compared exactly
     */
    private function isLower(string $amount, string $than): bool
    {
        $amount = $this->significantDigits($amount);
        $than = $this->significantDigits($than);

        return strlen($amount) < strlen($than) || (strlen($amount) === strlen($than) && strcmp($amount, $than) < 0);
    }

    /**
     * Amount in hundredths without leading zeros, e.g. "0.50" is "50", "12" is "1200" and "0.00" is ""
     */
    private function significantDigits(string $amount): string
    {
        [$units, $decimals] = array_pad(explode('.', $amount, 2), 2, '00');

        return ltrim($units . $decimals, '0');
    }

    /**
     * Non-ASCII characters are percent-encoded before validating, so a URL with accented letters in its path is accepted
     */
    private function isAbsoluteUrl(string $url): bool
    {
        $encoded = preg_replace_callback('/[^\x00-\x7F]+/', static fn(array $match): string => rawurlencode($match[0]), $url);
        $scheme = parse_url($url, PHP_URL_SCHEME);

        return is_string($encoded)
            && filter_var($encoded, FILTER_VALIDATE_URL) !== false
            && in_array(is_string($scheme) ? strtolower($scheme) : null, ['http', 'https'], true);
    }

    private function isUtf8(string $value): bool
    {
        return preg_match('//u', $value) === 1;
    }

    /**
     * Encode a CSV line as OpenAI describes it: cells with commas, quotes or line breaks are quoted and quotes are doubled.
     * Lines are built as strings instead of with fputcsv(), which does not report partial writes, so every write can be checked
     *
     * @param string[] $values
     */
    private function csvLine(array $values): string
    {
        $cells = array_map(
            static fn(string $value): string => preg_match('/[",\r\n]/', $value) === 1 ? '"' . str_replace('"', '""', $value) . '"' : $value,
            $values,
        );

        return implode(',', $cells) . "\n";
    }

    /**
     * Copy the buffer to the stream in chunks, so the whole CSV is never held in memory
     *
     * @param resource $buffer
     * @param resource $stream
     */
    private function copy(mixed $buffer, mixed $stream): void
    {
        while (!feof($buffer)) {
            $chunk = fread($buffer, 8192);

            if ($chunk === false) {
                throw new RuntimeException('Could not read the generated feed');
            }

            $this->write($stream, $chunk);
        }

        if (!fflush($stream)) {
            throw new RuntimeException('Could not write the feed: flush failed');
        }
    }

    /**
     * Write the whole chunk, retrying partial writes, and fail when the stream stops accepting data.
     * The write warning becomes the exception message, so callers get the same error whatever their error handler does
     *
     * @param resource $stream
     */
    private function write(mixed $stream, string $data): void
    {
        while ($data !== '') {
            error_clear_last();
            $written = @fwrite($stream, $data);

            if ($written === false || $written === 0) {
                throw new RuntimeException('Could not write the feed: ' . (error_get_last()['message'] ?? 'the stream accepts no more data'));
            }

            $data = substr($data, $written);
        }
    }
}
