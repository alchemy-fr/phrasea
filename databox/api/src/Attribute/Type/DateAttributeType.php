<?php

declare(strict_types=1);

namespace App\Attribute\Type;

use App\Attribute\AttributeInterface;

class DateAttributeType extends DateTimeAttributeType
{
    public const string NAME = 'date';

    #[\Override]
    public function getGroupValueLabel($value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value)
                ->setTime(0, 0)
                ->format(\DateTimeInterface::ATOM);
        }

        return parent::getGroupValueLabel($value);
    }

    #[\Override]
    public function normalizeElasticsearchValue(?string $value): mixed
    {
        $value = parent::normalizeElasticsearchValue($value);

        return $value ? substr((string) $value, 0, 10) : null;
    }

    /**
     * @return \DateTimeImmutable|null
     */
    #[\Override]
    public function denormalizeValue(?string $value): mixed
    {
        if (null === $value) {
            return null;
        }

        $value = trim($value);
        if ('' === $value) {
            return null;
        }

        try {
            $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
            if (false === $date) {
                $date = parent::denormalizeValue($value);
            }

            if ($date instanceof \DateTimeInterface) {
                return $date;
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    #[\Override]
    public function getStringValue(?string $value, ?string $locale): string
    {
        $date = $this->denormalizeValue($value);
        if ($date) {
            if (AttributeInterface::NO_LOCALE === $locale) {
                $locale = 'en';
            }
            $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::LONG, \IntlDateFormatter::NONE);

            return $formatter->format($date);
        }

        return '';
    }
}
