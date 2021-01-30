<?php

declare(strict_types=1);

namespace Sokil\Merchant\ProductFeed\Model\ProductField;

class Url extends AbstractScalarField
{
    public function __construct(string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new \UnexpectedValueException('Invalid URL');
        }

        parent::__construct($value);
    }
}