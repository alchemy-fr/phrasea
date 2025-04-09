<?php

namespace App\Elasticsearch\AQL\Function;

final readonly class ConcatFunction implements AQLFunctionInterface
{
    public function resolve(array $arguments): mixed
    {
        return sprintf('%s%s', $arguments[0], $arguments[1]);
    }

    public static function getName(): string
    {
        return 'concat';
    }

    public static function getArguments(): array
    {
        return [
            new Argument('left', TypeEnum::STRING, ''),
            new Argument('right', TypeEnum::STRING, ''),
        ];
    }

    public function getScript(array $arguments): string
    {
        return sprintf('%s.Concat(%s)', $arguments[0], $arguments[1]);
    }
}
