<?php

declare(strict_types=1);

namespace App\Elasticsearch\AQL\Function;

final readonly class SubstringFunction implements AQLFunctionInterface
{
    /**
     * PHP substr() in Painless, for a script to give the same result as a constant: 0-based start,
     * a length rather than an end index, negative start and length counted from the end, out of
     * range bounds tolerated.
     */
    private const string SCRIPT_DECLARATION = <<<'PAINLESS'
        String aqlSubstring(def s, def start, def length) {
            String v = '' + s;
            int n = v.length();
            int b = (int) start;
            if (b < 0) { b = n + b < 0 ? 0 : n + b; } else if (b > n) { return ''; }
            int e = n;
            if (length != null) {
                int l = (int) length;
                e = l < 0 ? n + l : (l > n - b ? n : b + l);
            }
            return e <= b ? '' : v.substring(b, e);
        }
        PAINLESS;

    public function resolve(array $arguments): mixed
    {
        return substr((string) $arguments[0], $arguments[1], $arguments[2] ?? null);
    }

    public static function getName(): string
    {
        return 'substring';
    }

    public static function getArguments(): array
    {
        return [
            new Argument('string', TypeEnum::STRING, 'The string to extract the substring from.'),
            new Argument('start', TypeEnum::INTEGER, 'The starting position of the substring.'),
            new Argument('length', TypeEnum::INTEGER, 'The length of the substring.', required: false),
        ];
    }

    public function getScript(array $arguments): string
    {
        return sprintf('aqlSubstring(%s, %s, %s)', $arguments[0], $arguments[1], $arguments[2] ?? 'null');
    }

    public function getScriptDeclaration(): string
    {
        return self::SCRIPT_DECLARATION;
    }
}
