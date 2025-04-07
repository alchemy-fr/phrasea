<?php

namespace App\Elasticsearch\AQL\Function;

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
        throw new \InvalidArgumentException(sprintf('Script generation is not supported for %s yet', self::getName()));
    }
}
