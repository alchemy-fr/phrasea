<?php

declare(strict_types=1);

namespace App\Util;

abstract class ArrayUtil
{
    public static function arrayAreSame(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }

        if (array_all(array_keys($a), fn ($key): bool => is_int($key))) {
            if (!array_all(array_keys($b), fn ($key): bool => is_int($key))) {
                return false;
            }

            $stack = $b;
            foreach ($a as $v) {
                $found = false;
                foreach ($stack as $k2 => $v2) {
                    if (self::valuesAreSame($v, $v2)) {
                        unset($stack[$k2]);
                        $found = true;
                        break;
                    }
                }

                if (!$found) {
                    return false;
                }
            }
        } else {
            foreach ($a as $k => $v) {
                if (array_key_exists($k, $b)) {
                    if (!self::valuesAreSame($v, $b[$k])) {
                        return false;
                    }
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    private static function valuesAreSame(mixed $a, mixed $b): bool
    {
        if (is_array($a)) {
            if (!is_array($b)) {
                return false;
            }

            if (!self::arrayAreSame($a, $b)) {
                return false;
            }
        } elseif ($a !== $b) {
            return false;
        }

        return true;
    }
}
