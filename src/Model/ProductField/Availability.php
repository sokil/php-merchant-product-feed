<?php

declare(strict_types=1);

namespace Sokil\Merchant\ProductFeed\Model\ProductField;

class Availability extends AbstractEnumerableField
{
    /**
     * Item ships immediately
     */
    public const IN_STOCK = 1;

    /**
     * Ships in 1-2 weeks
     */
    public const AVAILABLE_FOR_ORDER = 2;

    /**
     * Not available in current stock
     */
    public const OUT_OF_STOCK = 3;

    /**
     * Discontinued
     */
    public const DISCONTINUED = 4;
}