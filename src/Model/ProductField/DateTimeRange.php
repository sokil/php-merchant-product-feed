<?php

declare(strict_types=1);

namespace Sokil\Merchant\ProductFeed\Model\ProductField;

class DateTimeRange extends AbstractField
{
    /**
     * @var \DateTimeImmutable
     */
    private $dateTimeFrom;

    /**
     * @var \DateTimeImmutable
     */
    private $dateTimeTo;

    /**
     * @param \DateTimeImmutable $dateTimeFrom
     * @param \DateTimeImmutable $dateTimeTo
     */
    public function __construct(\DateTimeImmutable $dateTimeFrom, \DateTimeImmutable $dateTimeTo)
    {
        if ($dateTimeFrom >= $dateTimeTo) {
            throw new \LogicException('From date must be greater than to date');
        }

        $this->dateTimeFrom = $dateTimeFrom;
        $this->dateTimeTo = $dateTimeTo;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getDateTimeFrom(): \DateTimeImmutable
    {
        return $this->dateTimeFrom;
    }

    /**
     * @return \DateTimeImmutable
     */
    public function getDateTimeTo(): \DateTimeImmutable
    {
        return $this->dateTimeTo;
    }
}