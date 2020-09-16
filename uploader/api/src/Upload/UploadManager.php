<?php

declare(strict_types=1);

namespace App\Upload;

use Aws\S3\S3Client;

class UploadManager
{
    private S3Client $internalClient;
    private S3Client $externalClient;
    private string $uploadBucket;

    public function __construct(S3Client $internalClient, S3Client $externalClient, string $uploadBucket)
    {
        $this->internalClient = $internalClient;
        $this->uploadBucket = $uploadBucket;
        $this->externalClient = $externalClient;
    }

    public function prepareMultipartUpload(string $path, string $contentType)
    {
        return $this->internalClient->createMultipartUpload([
            'Bucket' => $this->uploadBucket,
            'Key' => $path,
            'ContentType' => $contentType,
        ]);
    }

    public function cancelMultipartUpload(string $path, string $uploadId): void
    {
        $this->internalClient->abortMultipartUpload([
            'Bucket' => $this->uploadBucket,
            'Key' => $path,
            'UploadId' => $uploadId,
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

        $cmd = $this->externalClient->getCommand('UploadPart', $params);

        $request = $this->externalClient->createPresignedRequest($cmd, '+30 minutes');

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

        $this->internalClient->completeMultipartUpload($params);
    }
}
