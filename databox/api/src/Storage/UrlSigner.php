<?php

declare(strict_types=1);

namespace App\Storage;

use Alchemy\StorageBundle\Cdn\CloudFrontUrlGenerator;
use Aws\S3\S3Client;

class UrlSigner
{
    private S3Client $client;
    private string $bucketName;
    private int $ttl;
    private CloudFrontUrlGenerator $cloudFrontUrlGenerator;

    public function __construct(S3Client $client, string $bucketName, int $ttl, CloudFrontUrlGenerator $cloudFrontUrlGenerator)
    {
        $this->client = $client;
        $this->bucketName = $bucketName;
        $this->ttl = $ttl;
        $this->cloudFrontUrlGenerator = $cloudFrontUrlGenerator;
    }

    public function getSignedUrl(string $path, array $options = []): string
    {
        if ($this->cloudFrontUrlGenerator->isEnabled()) {
            return $this->cloudFrontUrlGenerator->getSignedUrl($path);
        }

        $cmd = $this->client->getCommand('GetObject', array_merge([
            'Bucket' => $this->bucketName,
            'Key' => ltrim($path, '/'),
        ], $options));

        $request = $this->client->createPresignedRequest($cmd, time() + $this->ttl);

        return (string) $request->getUri();
    }
}
