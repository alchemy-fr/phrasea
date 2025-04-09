<?php

namespace App\Tests\AQL;

use App\Elasticsearch\AQL\Function\AQLFunctionInterface;

final readonly class MockNowFunction implements AQLFunctionInterface
{
    final public const int VALUE = 1234567890;

    public function resolve(array $arguments): mixed
    {
        return self::VALUE;
    }

    public function getScript(array $arguments): string
    {
        return '';
    }

    public static function getName(): string
    {
        return 'now';
    }

    public static function getArguments(): array
    {
        return [];
    }
}
