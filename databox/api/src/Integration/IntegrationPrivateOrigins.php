<?php

declare(strict_types=1);

namespace App\Integration;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The origins (scheme, host and port) that the base URL of a Phraseanet or Expose integration
 * may reach on the private network: those of the Expose and the Phraseanet of the instance, and
 * the ones listed in INTEGRATION_PRIVATE_ORIGINS. The base URL is set by a workspace editor, so
 * any other host is a remote service.
 */
final readonly class IntegrationPrivateOrigins
{
    private const array DEFAULT_PORTS = [
        'http' => 80,
        'https' => 443,
    ];

    /**
     * @var string[]
     */
    private array $origins;

    public function __construct(
        #[Autowire('%env(default::EXPOSE_API_URL)%')]
        ?string $exposeApiUrl,
        #[Autowire('%env(default::PWI_PHRASEANET_URL)%')]
        ?string $phraseanetUrl,
        // Comma separated
        #[Autowire('%env(default::INTEGRATION_PRIVATE_ORIGINS)%')]
        ?string $privateOrigins,
    ) {
        $origins = [];
        foreach ([$exposeApiUrl, $phraseanetUrl, ...explode(',', $privateOrigins ?? '')] as $url) {
            $origin = self::getOrigin(null !== $url ? trim($url) : null);
            if (null !== $origin) {
                $origins[$origin] = $origin;
            }
        }

        $this->origins = array_values($origins);
    }

    public function isAllowed(string $url): bool
    {
        $origin = self::getOrigin($url);

        return null !== $origin && in_array($origin, $this->origins, true);
    }

    /**
     * $client as is for an allowed origin, kept away from the private network otherwise.
     */
    public function getClient(HttpClientInterface $client, string $baseUrl): HttpClientInterface
    {
        return $this->isAllowed($baseUrl) ? $client : new NoPrivateNetworkHttpClient($client);
    }

    private static function getOrigin(?string $url): ?string
    {
        if (null === $url || '' === $url) {
            return null;
        }

        $parts = parse_url($url);
        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        if (!isset(self::DEFAULT_PORTS[$scheme])) {
            return null;
        }

        return sprintf('%s://%s:%d', $scheme, strtolower($parts['host']), $parts['port'] ?? self::DEFAULT_PORTS[$scheme]);
    }
}
