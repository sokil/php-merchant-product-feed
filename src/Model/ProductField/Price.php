<?php

declare(strict_types=1);

namespace Sokil\Merchant\ProductFeed\Model\ProductField;

class Price extends AbstractField
{
    /**
     * @var string
     */
    private $amount;

    /**
     * @see https://en.wikipedia.org/wiki/ISO_4217
     *
     * @var string
     */
    private $iso4210Currency;

    public function __construct(string $amount, string $iso4210Currency)
    {
        if (!preg_match('/\d+(\.\d{2})?/', $amount)) {
            throw new \InvalidArgumentException('Amount must be number with "." as decimal point');
        }

        if (!preg_match('/[A-Z]{3]/', $iso4210Currency)) {
            throw new \InvalidArgumentException('Amount must be number with "." as decimal point');
        }

        $this->amount = $amount;
        $this->iso4210Currency = $iso4210Currency;

        $this->__construct($amount . ' ' . $iso4210Currency);
    }

    /**
     * @return string
     */
    public function getAmount(): string
    {
        return $this->amount;
    }

    /**
     * @return string
     */
    public function getIso4210Currency(): string
    {
        return $this->iso4210Currency;
    }
}