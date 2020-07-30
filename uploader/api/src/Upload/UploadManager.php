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

    public function prepareMultipartUpload(string $path, string $contentType)
    {
        return $this->client->createMultipartUpload([
            'Bucket' => $this->uploadBucket,
            'Key' => $path,
            'ContentType' => $contentType,
        ]);
    }

    public function getSignedUrl(string $uploadId, string $path, int $partNumber): string
    {
        $params = [
            'Bucket' => $this->uploadBucket,
			'Key' => $path,
			'PartNumber' => $partNumber,
			'UploadId' => $uploadId,
        ];

        $cmd = $this->client->getCommand('UploadPart', $params);

        $request = $this->client->createPresignedRequest($cmd, '+30 minutes');

        return (string) $request->getUri();
    }

    public function markComplete(string $uploadId, string $filename, array $parts): void
    {
        $params = [
            'Bucket' => $this->uploadBucket,
			'Key' => $filename,
			'MultipartUpload' => [
			    'Parts' => $parts,
            ],
			'UploadId' => $uploadId,
        ];

        $this->client->completeMultipartUpload($params);
    }
}
