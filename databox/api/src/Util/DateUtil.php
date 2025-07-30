<?php

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
            return new \DateTimeImmutable('@'.$date);
        } elseif (is_string($date)) {
            $value = $date;
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
                if (1 === preg_match($tryout['p'], $date, $matches)) {
                    $args = array_map(fn (string $a): string => (int) $matches[$a], $tryout['m']);
                    $value = vsprintf($tryout['f'], $args);
                    break;
                }
            }

            if (false === $value = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $value)) {
                return null;
            }

            return $value;
        }

        return null;
    }
}
