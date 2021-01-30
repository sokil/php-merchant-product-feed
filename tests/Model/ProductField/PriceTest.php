<?php

declare(strict_types=1);

namespace Sokil\Merchant\ProductFeed\Formatter\Model\ProductField;

use PHPUnit\Framework\TestCase;
use Sokil\Merchant\ProductFeed\Model\ProductField\Price;

class PriceTest extends TestCase
{
    public function getConstructPositiveDataProvider()
    {
        return [
            ['1.01', 'UAH'],
            ['12.01', 'UAH'],
            ['123.01', 'UAH'],
            ['1234.01', 'UAH'],
        ];
    }

    /**
     * @dataProvider getConstructPositiveDataProvider
     */
    public function testConstructPositive(string $amount, string $currency)
    {
        $price = new Price($amount, $currency);

        $this->assertSame($amount, $price->getAmount());
        $this->assertSame($currency, $price->getIso4210Currency());
    }
}