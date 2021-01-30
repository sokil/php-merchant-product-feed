<?php

declare(strict_types=1);

namespace Sokil\Merchant\ProductFeed\Model\ProductField;

/**
 * @see https://developers.facebook.com/docs/commerce-platform/catalog/fields Facebook field definition
 */
abstract class AbstractEnumerableField extends AbstractScalarField
{
    public function __construct($value)
    {
        if (!$this->isValid($value)) {
            throw new \UnexpectedValueException('Value not in set');
        }

        parent::__construct($value);
    }

    private function isValid($value): bool
    {
        static $values;

        $class = static::class;

        if (!isset($values[$class])) {
            $reflection = new \ReflectionClass($class);
            $values[$class] = $reflection->getConstants();
        }

        return in_array($value, $values[$class]);
    }
}