<?php

declare(strict_types=1);

namespace App\Elasticsearch\AQL\Function;

use App\Elasticsearch\AQL\InvalidAQLException;
use App\Util\DateUtil;

abstract readonly class AbstractDateFunction implements AQLFunctionInterface
{
    protected function normalizeDate(mixed $date): \DateTimeImmutable
    {
        $d = DateUtil::normalizeDate($date);
        if ($d instanceof \DateTimeImmutable) {
            return $d;
        }

        throw new InvalidAQLException(sprintf('Function "%s" expects a date, got %s', static::getName(), is_scalar($date) ? sprintf('"%s"', $date) : get_debug_type($date)));
    }

    protected function createInterval(mixed $interval): \DateInterval
    {
        if (is_string($interval)) {
            try {
                return new \DateInterval($interval);
            } catch (\Exception) {
            }
        }

        throw new InvalidAQLException(sprintf('Function "%s" expects an ISO 8601 interval (e.g. "PT1H"), got %s', static::getName(), is_scalar($interval) ? sprintf('"%s"', $interval) : get_debug_type($interval)));
    }

    public function getScript(array $arguments): string
    {
        throw new InvalidAQLException(sprintf('Function "%s" is not supported in script conditions', static::getName()));
    }
}
