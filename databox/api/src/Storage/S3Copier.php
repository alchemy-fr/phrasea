<?php

declare(strict_types=1);

namespace App\Storage;

use App\Asset\FileFetcher;
use App\Entity\Core\File;
use Aws\S3\S3Client;

class S3Copier
{
    public function __construct(private readonly FileFetcher $fileFetcher)
    {
    }

    public function copyToS3(File $file, string $bucket, string $key, array $options): void
    {
        $client = $this->createClient($options);

        $src = $this->fileFetcher->getFile($file);

        $fd = fopen($src, 'r');
        try {
            $client->upload($bucket, $key, $fd);
        } finally {
            fclose($fd);
            unlink($src);
        }
    }

    private function createClient(array $options): S3Client
    {
        return new S3Client([
            'region' => $options['region'],
            'credentials' => [
                'key' => $options['accessKeyId'],
                'secret' => $options['accessKeySecret'],
            ],
            'version' => 'latest',
        ]);
    }
}
