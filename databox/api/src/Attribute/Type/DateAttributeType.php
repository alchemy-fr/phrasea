<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;

class DateAttributeType extends DateTimeAttributeType
{
    public static function getName(): string
    {
        return 'date';
    }

    public function getGroupValueLabel($value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            if ($value instanceof DateTimeImmutable) {
                $date = DateTime::createFromImmutable($value);
            } else {
                $date = clone $value;
            }

            $date->setTime(0, 0, 0);

            return $date->format(DateTimeInterface::ATOM);
        }

        return $value ?? '';
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function denormalizeValue(?string $value)
    {
        if (null === $value) {
            return null;
        }

        try {
            $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
            if (false === $date) {
                return parent::denormalizeValue($value);
            }

            return $date;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
