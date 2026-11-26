<?php

declare(strict_types=1);

namespace Alchemy\WebhookBundle\Webhook;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The URL of a webhook is set by an admin: it may only reach the private network for the
 * origins (scheme, host and port) listed in WEBHOOK_PRIVATE_ORIGINS, comma separated.
 */
class WebhookApiClientFactory
{
    private const array DEFAULT_PORTS = [
        'http' => 80,
        'https' => 443,
    ];

    /**
     * @var string[]
     */
    private readonly array $privateOrigins;

    public function __construct(
        private readonly HttpClientInterface $client,
        #[Autowire('%env(default::WEBHOOK_PRIVATE_ORIGINS)%')]
        ?string $privateOrigins = null,
    ) {
        $origins = [];
        foreach (explode(',', $privateOrigins ?? '') as $url) {
            $origin = self::getOrigin(trim($url));
            if (null !== $origin) {
                $origins[$origin] = $origin;
            }
        }

        $this->privateOrigins = array_values($origins);
    }

    public function create(string $url): HttpClientInterface
    {
        $client = $this->isPrivateOriginAllowed($url) ? $this->client : new NoPrivateNetworkHttpClient($this->client);

        return $client->withOptions([
            'max_redirects' => 0,
        ]);
    }

    public function isPrivateOriginAllowed(string $url): bool
    {
        $origin = self::getOrigin($url);

        return null !== $origin && in_array($origin, $this->privateOrigins, true);
    }

    private static function getOrigin(string $url): ?string
    {
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
