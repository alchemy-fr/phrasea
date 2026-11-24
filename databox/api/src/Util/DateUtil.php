<?php

declare(strict_types=1);

namespace App\Util;

abstract class DateUtil
{
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
            $value = trim((string) $date);
            foreach ([
                [
                    'p' => '#^(\d{4})\D(\d{2})\D(\d{2})$#',
                    'f' => '%04d-%02d-%02dT00:00:00Z',
                    'm' => [1, 2, 3]],
                [
                    'p' => '#^(\d{4})\D(\d{2})\D(\d{2})\D(\d{2})\D(\d{2})\D(\d{2})$#',
                    'f' => '%04d-%02d-%02dT%02d:%02d:%02dZ',
                    'm' => [1, 2, 3, 4, 5, 6]],
                [
                    'p' => '#^(\d{4})\D(\d{2})\D(\d{2})T(\d{2})\D(\d{2})$#',
                    'f' => '%04d-%02d-%02dT%02d:%02d:00Z',
                    'm' => [1, 2, 3, 4, 5]],
            ] as $tryout) {
                $matches = [];
                if (1 === preg_match($tryout['p'], $value, $matches)) {
                    $args = array_map(fn (string $a): string => $matches[$a], $tryout['m']);
                    $value = vsprintf($tryout['f'], $args);
                    break;
                }
            }

            $parsed = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value);
            if (false === $parsed) {
                // Browsers (Date.toISOString()) send fractional seconds, e.g. 2026-04-26T00:00:00.000Z
                $parsed = \DateTimeImmutable::createFromFormat('Y-m-d\\TH:i:s.uP', $value);
            }
            if (false === $value = $parsed) {
                return null;
            }

            $formatted = $value->format(\DateTimeInterface::ATOM);
            if (str_starts_with($formatted, '-') || str_starts_with($formatted, '0000-00-00')) {
                // NULL dates
                return null;
            }

            return $value;
        }

        return null;
    }
}
