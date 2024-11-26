<?php

namespace Alchemy\ConfiguratorBundle\Pusher;

use Aws\S3\S3Client;

final readonly class BucketPusher
{
    public function __construct(
        private S3Client $s3Client,
        private string $bucketName,
        private string $pathPrefix = '',
    )
    {
    }

    public function pushToBucket(string $path, string $data): void
    {
        $this->s3Client->putObject([
            'Bucket' => $this->bucketName,
            'Key' => $this->pathPrefix.$path,
            'Body' => $data,
            'ACL' => 'public-read',
        ]);
    }
}
