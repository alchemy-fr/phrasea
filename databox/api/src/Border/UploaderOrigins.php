<?php

declare(strict_types=1);

namespace App\Border;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The origins (scheme, host and port) of the Uploader of this instance: its API, and the
 * S3 storage on which it presigns the URLs of the uploaded files. Only them may be reached
 * on the private network: the base URL of an Uploader integration is set by a workspace
 * editor, so any other Uploader is a remote service, as are the URLs it answers.
 */
final readonly class UploaderOrigins
{
    private const array DEFAULT_PORTS = [
        'http' => 80,
        'https' => 443,
    ];

    private ?string $apiOrigin;
    private ?string $storageOrigin;

    public function __construct(
        #[Autowire('%env(default::UPLOADER_API_URL)%')]
        ?string $uploaderApiUrl,
        // The Uploader stores its files on the S3 of the stack, shared with Databox
        #[Autowire('%env(default::S3_ENDPOINT)%')]
        ?string $uploaderStorageUrl,
    ) {
        $this->apiOrigin = self::getOrigin($uploaderApiUrl);
        $this->storageOrigin = self::getOrigin($uploaderStorageUrl);
    }

    public function isUploaderApi(string $url): bool
    {
        $origin = self::getOrigin($url);

        return null !== $origin && $origin === $this->apiOrigin;
    }

    /**
     * Whether $url is a file of the Uploader of this instance, served by its storage or its API.
     */
    public function isUploaderFile(string $url): bool
    {
        $origin = self::getOrigin($url);

        return null !== $origin && ($origin === $this->storageOrigin || $origin === $this->apiOrigin);
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
