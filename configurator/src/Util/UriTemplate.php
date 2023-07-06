<?php

declare(strict_types=1);

namespace App\Util;

final class UriTemplate
{
    public static function resolve(string $uri, array $params = []): string
    {
        $replacements = [];

        foreach ($params as $k => $v) {
            $replacements['{'.$k.'}'] = $v;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $uri);
    }
}
