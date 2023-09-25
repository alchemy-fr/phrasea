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

    public function denormalizeValue(?string $value): ?\DateTimeImmutable
    {
        if (null === $value) {
            return null;
        }

        try {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
            if (false === $date) {
                return parent::denormalizeValue($value);
            }

            return $date;
        } catch (\Throwable) {
            return null;
        }
    }
}
