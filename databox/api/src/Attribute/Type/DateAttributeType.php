<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use DateTimeImmutable;
use DateTimeInterface;

class DateAttributeType extends AbstractAttributeType
{
    public static function getName(): string
    {
        return 'date';
    }

    public function getElasticSearchType(): string
    {
        return 'date';
    }

    /**
     * @param string|DateTimeInterface $value
     *
     * @return string
     */
    public function normalizeValue($value)
    {
        if (!$value instanceof DateTimeInterface) {
            $value = new DateTimeImmutable($value);
        }

        return $value->format(DateTimeInterface::ATOM);
    }

    /**
     * @param string $value
     *
     * @return DateTimeImmutable
     */
    public function denormalizeValue($value)
    {
        return new DateTimeImmutable($value);
    }
}
