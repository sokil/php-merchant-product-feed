<?php

declare(strict_types=1);

namespace Sokil\Merchant\ProductFeed\Model\ProductField;

class Condition extends AbstractEnumerableField
{
    public const NEW = 1;
    public const REFURBISHED = 2;
    public const USED = 3;
}