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

    public function getSignedUrl(string $path, array $options = []): string
    {
        if ($this->cloudFrontUrlGenerator->isEnabled()) {
            return $this->cloudFrontUrlGenerator->getSignedUrl($path, $options);
        }

        $cmdOptions = [];
        if ($options['download'] ?? false) {
            $cmdOptions['ResponseContentDisposition'] = sprintf(
                'attachment; filename=%s',
                basename($path)
            );
        }

        $cmd = $this->client->getCommand('GetObject', array_merge([
            'Bucket' => $this->bucketName,
            'Key' => ltrim($this->pathPrefix.$path, '/'),
        ], $cmdOptions));

        $request = $this->client->createPresignedRequest($cmd, time() + ($options['ttl'] ?? $this->ttl));

        return (string) $request->getUri();
    }
}
