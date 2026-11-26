<?php

declare(strict_types=1);

namespace App\Util;

abstract class DateUtil
{
    /**
     * A date, optionally followed by a time (minutes or seconds precision, optional fractional seconds)
     * and a time zone. Any non-digit separates the date parts (2026-04-26, EXIF 2026:04:26…).
     */
    private const string DATE_PATTERN = '#^(\d{4})\D(\d{2})\D(\d{2})(?:[T\s](\d{2})\D(\d{2})(?:\D(\d{2})(?:\.(\d+))?)?)?\s*(Z|[+-]\d{2}:?\d{2})?$#i';

    /**
     * A string without a time zone (e.g. 2026-04-26 10:11 or EXIF DateTimeOriginal) is read in UTC.
     * Fractional seconds are kept up to the microsecond, the further digits are dropped.
     * A date that does not exist (2026-02-30, 2026-00-12…) or of year 0 is no date.
     */
    public static function normalizeDate(mixed $date): ?\DateTimeImmutable
    {
        if (empty($date)) {
            return null;
        }

        if ($date instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($date);
        } elseif (is_int($date)) {
            if ($date <= 0) {
                return null;
            }

            return new \DateTimeImmutable('@'.$date);
        } elseif (is_string($date) || $date instanceof \Stringable) {
            $matches = [];
            if (1 !== preg_match(self::DATE_PATTERN, trim((string) $date), $matches)) {
                return null;
            }

            $value = sprintf(
                '%s-%s-%sT%s:%s:%s.%s%s',
                $matches[1],
                $matches[2],
                $matches[3],
                ($matches[4] ?? '') ?: '00',
                ($matches[5] ?? '') ?: '00',
                ($matches[6] ?? '') ?: '00',
                substr(($matches[7] ?? '') ?: '0', 0, 6),
                strtoupper(($matches[8] ?? '') ?: 'Z'),
            );

            $parsed = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i:s.uP', $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if (false === $parsed || (false !== $errors && $errors['warning_count'] > 0)) {
                // Out of range values are not rolled over (2026-02-30 is not 2026-03-02)
                return null;
            }

            if (str_starts_with($parsed->format(\DateTimeInterface::ATOM), '0000-')) {
                // NULL dates
                return null;
            }

            return $parsed;
        }

        return null;
    }
}
