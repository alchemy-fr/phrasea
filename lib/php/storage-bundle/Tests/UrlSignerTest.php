<?php

declare(strict_types=1);

namespace Alchemy\StorageBundle\Tests;

use Alchemy\StorageBundle\Cdn\CloudFrontUrlGenerator;
use Alchemy\StorageBundle\Storage\UrlSigner;
use Aws\CloudFront\CloudFrontClient;
use Aws\S3\S3Client;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UrlSignerTest extends TestCase
{
    #[DataProvider('getOptions')]
    public function testS3ResponseHeaders(array $options, string $expectedType, ?string $expectedDisposition): void
    {
        $s3Client = new S3Client([
            'endpoint' => 'https://minio.phrasea.test',
            'region' => 'eu-central-1',
            'version' => 'latest',
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => 'ACCESS_KEY',
                'secret' => 'SECRET_KEY',
            ],
        ]);
        $urlSigner = new UrlSigner($s3Client, 'bucket', 60, $this->createCloudFrontUrlGenerator(null));

        $url = $urlSigner->getSignedUrl('a/b/foo.bin', $options);

        $this->assertStringStartsWith('https://minio.phrasea.test/bucket/a/b/foo.bin?', $url);
        $query = $this->getQuery($url);
        $this->assertSame($expectedType, $query['response-content-type'] ?? null);
        $this->assertSame($expectedDisposition, $query['response-content-disposition'] ?? null);
        $this->assertArrayHasKey('X-Amz-Signature', $query);
    }

    #[DataProvider('getOptions')]
    public function testCloudFrontResponseHeaders(array $options, string $expectedType, ?string $expectedDisposition): void
    {
        $generator = $this->createCloudFrontUrlGenerator('https://cdn.phrasea.test');
        $this->assertTrue($generator->isEnabled());

        $url = $generator->getSignedUrl('a/b/foo.bin', $options);

        $this->assertStringStartsWith('https://cdn.phrasea.test/prefix/a/b/foo.bin?', $url);
        $query = $this->getQuery($url);
        $this->assertSame($expectedType, $query['response-content-type'] ?? null);
        $this->assertSame($expectedDisposition, $query['response-content-disposition'] ?? null);
        $this->assertArrayHasKey('Signature', $query);
        $this->assertSame('KEY_PAIR_ID', $query['Key-Pair-Id']);
    }

    public static function getOptions(): array
    {
        $attachment = 'attachment; filename=foo.bin';

        return [
            'no option' => [[], 'application/octet-stream', $attachment],
            'inline without type' => [['inline' => true], 'application/octet-stream', $attachment],
            'image inline' => [['type' => 'image/jpeg', 'inline' => true], 'image/jpeg', null],
            'pdf inline' => [['type' => 'application/pdf', 'inline' => true], 'application/pdf', null],
            'image without inline' => [['type' => 'image/jpeg'], 'image/jpeg', $attachment],
            'image download' => [['type' => 'image/jpeg', 'inline' => true, 'download' => true], 'image/jpeg', $attachment],
            'svg inline' => [['type' => 'image/svg+xml', 'inline' => true], 'application/octet-stream', $attachment],
            'html inline' => [['type' => 'text/html', 'inline' => true], 'application/octet-stream', $attachment],
            'xml inline' => [['type' => 'application/xml', 'inline' => true], 'application/octet-stream', $attachment],
            'text download' => [['type' => 'text/plain', 'download' => true], 'application/octet-stream', $attachment],
        ];
    }

    private function createCloudFrontUrlGenerator(?string $url): CloudFrontUrlGenerator
    {
        $privateKey = null;
        if (null !== $url) {
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            openssl_pkey_export($key, $privateKey);
        }

        return new CloudFrontUrlGenerator(
            new CloudFrontClient([
                'region' => 'eu-central-1',
                'version' => '2020-05-31',
                'credentials' => false,
            ]),
            60,
            $url,
            $privateKey,
            'KEY_PAIR_ID',
            'prefix/',
        );
    }

    private function getQuery(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }
}
