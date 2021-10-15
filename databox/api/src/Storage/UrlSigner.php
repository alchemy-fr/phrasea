<?php

declare(strict_types=1);

namespace App\Storage;

use Aws\S3\S3Client;

class UrlSigner
{
    private S3Client $client;
    private string $bucketName;
    private int $ttl;

    public function __construct(S3Client $client, string $bucketName, int $ttl)
    {
        $this->client = $client;
        $this->bucketName = $bucketName;
        $this->ttl = $ttl;
    }

    public function getSignedUrl(string $path, array $options = []): string
    {
        $cmd = $this->client->getCommand('GetObject', array_merge([
            'Bucket' => $this->bucketName,
            'Key' => ltrim($path, '/'),
        ], $options));

        $request = $this->client->createPresignedRequest($cmd, time() + $this->ttl);

        return (string) $request->getUri();
    }
}
