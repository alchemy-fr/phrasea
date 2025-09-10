<?php

declare(strict_types=1);

namespace App\Attribute\Type;

class DateAttributeType extends DateTimeAttributeType
{
    public const string NAME = 'date';

    public static function getName(): string
    {
        return self::NAME;
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

    public function normalizeElasticsearchValue(?string $value)
    {
        $value = parent::normalizeElasticsearchValue($value);

        return $value ? substr($value, 0, 10) : null;
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
