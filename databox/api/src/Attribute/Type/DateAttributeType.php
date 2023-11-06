<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class DateAttributeType extends DateTimeAttributeType
{
    public static function getName(): string
    {
        return 'date';
    }

    public function getGroupValueLabel($value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value)
                ->setTime(0, 0)
                ->format(\DateTimeInterface::ATOM);
        }

        return parent::getGroupValueLabel($value);
    }

    public function denormalizeValue(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        try {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
            if (false === $date) {
                $date = parent::denormalizeValue($value);
            }

            if ($date instanceof \DateTimeInterface) {
                return $date->format('Y-m-d');
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }
}
