<?php

namespace App\Elasticsearch\AQL\Function;

use App\Elasticsearch\Facet\FacetInterface;

abstract readonly class AbstractDateFunction implements AQLFunctionInterface
{
    protected function normalizeDate(mixed $date): \DateTimeImmutable
    {
        if ($date instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($date);
        }

        if (is_int($date)) {
            return new \DateTimeImmutable('@' . $date);
        } elseif (is_string($date)) {
            return new \DateTimeImmutable($date);
        }

        throw new \InvalidArgumentException('Invalid date format %s', get_debug_type($date));
    }
}
