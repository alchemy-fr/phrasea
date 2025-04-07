<?php

namespace App\Elasticsearch\AQL\Function;

final readonly class SubstringFunction implements AQLFunctionInterface
{
    public function resolve(array $arguments): mixed
    {
        return substr($arguments[0], $arguments[1], $arguments[2] ?? null);
    }

    public static function getName(): string
    {
        return 'substring';
    }

    public static function getArguments(): array
    {
        return [
            new Argument('string', TypeEnum::STRING, 'The string to extract the substring from.'),
            new Argument('start', TypeEnum::NUMBER, 'The starting position of the substring.'),
            new Argument('length', TypeEnum::NUMBER, 'The length of the substring.', required: false),
        ];
    }

    public function getScript(array $arguments): string
    {
        $argCount = count($arguments);

        if ($argCount > 2) {
            return sprintf('%s.Substring(%d, %d)', $arguments[0], $arguments[1], $arguments[2]);
        }

        return sprintf('%s.Substring(%d)', $arguments[0], $arguments[1]);
    }
}
