<?php

declare(strict_types=1);

namespace App\Elasticsearch\AQL\Function;

use App\Elasticsearch\AQL\InvalidAQLException;

final readonly class NowFunction implements AQLFunctionInterface
{
    public function resolve(array $arguments): mixed
    {
        return time();
    }

    public static function getName(): string
    {
        return 'now';
    }

    public static function getArguments(): array
    {
        return [];
    }

    public function getScript(array $arguments): string
    {
        throw new InvalidAQLException(sprintf('Function "%s" is not supported in script conditions', self::getName()));
    }
}
