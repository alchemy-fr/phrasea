<?php

namespace App\Elasticsearch\AQL\Function;

use App\Util\DateUtil;

abstract readonly class AbstractDateFunction implements AQLFunctionInterface
{
    protected function normalizeDate(mixed $date): \DateTimeImmutable
    {
        $d = DateUtil::normalizeDate($date);
        if ($d instanceof \DateTimeImmutable) {
            return $d;
        }

        throw new \InvalidArgumentException('Invalid date format %s', get_debug_type($date));
    }
}
