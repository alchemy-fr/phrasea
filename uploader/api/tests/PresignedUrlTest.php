<?php

declare(strict_types=1);

namespace App\Tests;

use Arthem\RequestSignerBundle\RequestSigner;
use Arthem\RequestSignerBundle\Signer\AWSS3SignerAdapter;
use Aws\S3\S3Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;

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
        bool $usePathStyleEndpoint
    ): void
    {
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


        $psr17Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

        $httpFoundationFactory = new HttpFoundationFactory();

        $adapter = new AWSS3SignerAdapter($s3Client, $bucketName, 15);

        $requestSigner = new RequestSigner($psrHttpFactory, $httpFoundationFactory, ['default' => $adapter], 'default');

        $this->assertStringStartsWith(sprintf(
            $expected,
            $bucketName,
            $filePath
        ), $requestSigner->signUri(sprintf('%s/%s', $storageBaseUrl, $filePath), Request::create('http://localhost/')));
    }

    public function getConfigs(): array
    {
        return [
            ['https://minio.alchemy.local/%s/%s?', 'https://minio.alchemy.local', 'ue-west-1', 'uploader-deposit', true],
            ['https://%s.s3.ue-west-1.amazonaws.com/%s?', null, 'ue-west-1', 'uploader-deposit', false],
            ['https://%s.s3.ue-west-1.amazonaws.com/%s?', 'https://s3.ue-west-1.amazonaws.com', 'ue-west-1', 'uploader-deposit', false],
        ];
    }
}
