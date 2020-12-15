<?php

declare(strict_types=1);

namespace App\Upload;

use Aws\S3\S3Client;

class UploadManager
{
    private S3Client $client;
    private string $uploadBucket;

    public function __construct(S3Client $client, string $uploadBucket)
    {
        $this->client = $client;
        $this->uploadBucket = $uploadBucket;
    }

    public function createPutObjectSignedURL(string $path, string $contentType): string
    {
        $command = $this->client->getCommand('PutObject', array(
            'Bucket' => $this->uploadBucket,
            'Key' => $path,
            'ContentType' => $contentType,
        ));
        $request = $this->client->createPresignedRequest($command, '+30 minutes');

        return (string) $request->getUri();
    }
}
