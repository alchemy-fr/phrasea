<?php

declare(strict_types=1);

namespace App\Util;

abstract class Time
{
    public static function time2string(int $time): string
    {
        if (0 == $time) {
            return '0 seconds';
        }

        $t1 = new \DateTimeImmutable();
        $t2 = new \DateTimeImmutable("+$time seconds");
        $diff = $t1->diff($t2);
        $units = [
            'days' => 'day',
            'h' => 'hour',
            'i' => 'minute',
            's' => 'second',
        ];  // nominate units
        $result = [];
        foreach ($units as $k => $v) {
            if ($diff->$k != 0) {
                $result[] = $diff->$k.' '.$v.($diff->$k > 1 ? 's' : '');
            }
        }

        return implode(', ', $result);
    }
}
