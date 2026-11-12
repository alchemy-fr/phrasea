<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Storage;

use Alchemy\StorageBundle\Cdn\CloudFrontUrlGenerator;
use Aws\S3\S3Client;

final readonly class UrlSigner
{
    public function __construct(
        private S3Client $client,
        private string $bucketName,
        private int $ttl,
        private CloudFrontUrlGenerator $cloudFrontUrlGenerator,
        private string $pathPrefix = '',
    ) {
    }

    /**
     * Validity (in seconds) of the URLs produced by getSignedUrl() when no "ttl" option is given.
     */
    public function getTtl(): int
    {
        return $this->ttl;
    }

    /**
     * @param array{
     *     type?: string|null,
     *     inline?: bool,
     *     download?: bool,
     *     ttl?: int,
     * } $options "type" is the MIME type known by the application (never trust
     *           the one stored on the object); "inline" asks for an inline display,
     *           granted only to the types allowed by InlineContentTypePolicy;
     *           "download" forces an attachment
     */
    public function getSignedUrl(string $path, array $options = []): string
    {
        if ($this->cloudFrontUrlGenerator->isEnabled()) {
            return $this->cloudFrontUrlGenerator->getSignedUrl($path, $options);
        }

        $headers = InlineContentTypePolicy::getResponseHeaders(
            $options['type'] ?? null,
            basename($path),
            ($options['inline'] ?? false) && !($options['download'] ?? false),
        );

        $cmdOptions = [
            'ResponseContentType' => $headers['contentType'],
        ];
        if (null !== $headers['contentDisposition']) {
            $cmdOptions['ResponseContentDisposition'] = $headers['contentDisposition'];
        }

        $cmd = $this->client->getCommand('GetObject', array_merge([
            'Bucket' => $this->bucketName,
            'Key' => ltrim($this->pathPrefix.$path, '/'),
        ], $cmdOptions));

        $request = $this->client->createPresignedRequest($cmd, time() + ($options['ttl'] ?? $this->ttl));

        return (string) $request->getUri();
    }
}
