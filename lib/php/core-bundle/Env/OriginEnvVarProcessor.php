<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Env;

use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

/**
 * `%env(origin:FOO_URL)%` gives the origin of an URL (`scheme://host[:port]`, lowercased, the default
 * port of the scheme omitted), as sent by the browsers in the `Origin` header.
 * An empty or unparsable URL gives an empty string, which matches no origin.
 */
final readonly class OriginEnvVarProcessor implements EnvVarProcessorInterface
{
    private const array DEFAULT_PORTS = [
        'http' => 80,
        'https' => 443,
    ];

    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        return self::toOrigin((string) $getEnv($name));
    }

    public static function getProvidedTypes(): array
    {
        return [
            'origin' => 'string',
        ];
    }

    public static function toOrigin(string $url): string
    {
        $parts = parse_url(trim($url));
        if (false === $parts || empty($parts['scheme']) || empty($parts['host'])) {
            return '';
        }

        $scheme = strtolower($parts['scheme']);
        $origin = $scheme.'://'.strtolower($parts['host']);

        $port = $parts['port'] ?? null;
        if (null !== $port && $port !== (self::DEFAULT_PORTS[$scheme] ?? null)) {
            $origin .= ':'.$port;
        }

        return $origin;
    }
}
