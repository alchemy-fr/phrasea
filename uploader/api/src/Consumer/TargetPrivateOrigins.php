<?php

declare(strict_types=1);

namespace App\Consumer;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The origins (scheme, host and port) of the targets that may be notified on the private network:
 * the Databox API of this instance, and the ones listed in TARGET_PRIVATE_ORIGINS. The URL of a
 * target is set by an Uploader admin, so any other target is a remote service.
 */
final readonly class TargetPrivateOrigins
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
        #[Autowire('%env(default::DATABOX_API_URL)%')]
        ?string $databoxApiUrl,
        // Comma separated
        #[Autowire('%env(default::TARGET_PRIVATE_ORIGINS)%')]
        ?string $privateOrigins,
    ) {
        $origins = [];
        foreach ([$databoxApiUrl, ...explode(',', $privateOrigins ?? '')] as $url) {
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
