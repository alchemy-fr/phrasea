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

        foreach ($a as $k => $v) {
            if (array_key_exists($k, $b)) {
                if (is_array($v)) {
                    if (!is_array($b[$k])) {
                        return false;
                    }

                    if (!self::arrayAreSame($v, $b[$k])) {
                        return false;
                    }
                } else {
                    if ($v !== $b[$k]) {
                        return false;
                    }
                }
            } else {
                return false;
            }
        }

        return true;
    }
}
