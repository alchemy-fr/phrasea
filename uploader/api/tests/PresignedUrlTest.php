<?php

declare(strict_types=1);

namespace App\Tests;

use Alchemy\StorageBundle\Cdn\CloudFrontUrlGenerator;
use Alchemy\StorageBundle\Storage\UrlSigner;
use Aws\S3\S3Client;
use PHPUnit\Framework\TestCase;

class PresignedUrlTest extends TestCase
{
    /**
     * @dataProvider getConfigs
     */
    public function testPresignedURLWithMinio(
        string $expected,
        ?string $storageBaseUrl,
        string $region,
        string $bucketName,
        bool $usePathStyleEndpoint,
    ): void {
        $accessKey = 'ACCESS_KEY';
        $secretKey = 'SECRET_KEY';

        $s3Client = new S3Client([
            'endpoint' => $storageBaseUrl,
            'region' => $region,
            'bucket' => $bucketName,
            'version' => 'latest',
            'use_path_style_endpoint' => $usePathStyleEndpoint,
            'credentials' => [
                'key' => $accessKey,
                'secret' => $secretKey,
            ],
        ]);

        $filePath = 'a/b/c/foo.jpg';
        $params = [
            'Bucket' => $bucketName,
            'Key' => $filePath,
            'PartNumber' => 1,
            'UploadId' => 'upload-id',
        ];

        $cmd = $s3Client->getCommand('UploadPart', $params);
        $request = $s3Client->createPresignedRequest($cmd, '+30 minutes');

        $this->assertStringStartsWith(sprintf(
            $expected,
            $bucketName,
            $filePath
        ), (string) $request->getUri());

        /** @var CloudFrontUrlGenerator $cloudFrontUrlGenerator */
        $cloudFrontUrlGenerator = $this->createMock(CloudFrontUrlGenerator::class);
        $urlSigner = new UrlSigner($s3Client, $bucketName, 15, $cloudFrontUrlGenerator);

        $this->assertStringStartsWith(sprintf(
            $expected,
            $bucketName,
            $filePath
        ), $urlSigner->getSignedUrl($filePath));
    }

    public function getConfigs(): array
    {
        return [
            ['https://minio.phrasea.local/%s/%s?', 'https://minio.phrasea.local', 'ue-west-1', 'uploader-deposit', true],
            ['https://%s.s3.ue-west-1.amazonaws.com/%s?', null, 'ue-west-1', 'uploader-deposit', false],
            ['https://%s.s3.ue-west-1.amazonaws.com/%s?', 'https://s3.ue-west-1.amazonaws.com', 'ue-west-1', 'uploader-deposit', false],
        ];
    }
}
