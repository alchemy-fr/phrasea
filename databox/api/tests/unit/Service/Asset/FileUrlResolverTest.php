<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Asset;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use Alchemy\StorageBundle\Cdn\CloudFrontUrlGenerator;
use Alchemy\StorageBundle\Storage\UrlSigner;
use App\Entity\Core\File;
use App\Service\Asset\FileUrlResolver;
use Aws\CloudFront\CloudFrontClient;
use Aws\S3\S3Client;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class FileUrlResolverTest extends TestCase
{
    #[DataProvider('getFileTypes')]
    public function testResponseHeadersFollowTheFileType(?string $type, string $expectedType, ?string $expectedDisposition): void
    {
        $url = $this->createResolver()->resolveUrl($this->createFile('a/b/foo.bin', $type));

        $query = $this->getQuery($url);
        $this->assertSame($expectedType, $query['response-content-type'] ?? null);
        $this->assertSame($expectedDisposition, $query['response-content-disposition'] ?? null);
    }

    public static function getFileTypes(): array
    {
        $attachment = 'attachment; filename=foo.bin';

        return [
            'image' => ['image/jpeg', 'image/jpeg', null],
            'video' => ['video/mp4', 'video/mp4', null],
            'pdf' => ['application/pdf', 'application/pdf', null],
            'svg' => ['image/svg+xml', 'application/octet-stream', $attachment],
            'html' => ['text/html', 'application/octet-stream', $attachment],
            'unknown' => [null, 'application/octet-stream', $attachment],
        ];
    }

    public function testCachedUrlsDependOnTheType(): void
    {
        $resolver = $this->createResolver();
        $image = $this->createFile('a/b/foo.bin', 'image/png');
        $html = $this->createFile('a/b/foo.bin', 'text/html');

        $resolver->preloadUrls([$image]);
        $imageUrl = $resolver->resolveUrl($image);
        $htmlUrl = $resolver->resolveUrl($html);

        $this->assertSame('image/png', $this->getQuery($imageUrl)['response-content-type']);
        $this->assertSame('application/octet-stream', $this->getQuery($htmlUrl)['response-content-type']);
        $this->assertSame($imageUrl, $resolver->resolveUrl($image));
    }

    public function testExternalUrlIsUntouched(): void
    {
        $file = $this->createFile('https://example.com/foo.svg', 'image/svg+xml');
        $file->setStorage(File::STORAGE_URL);

        $this->assertSame('https://example.com/foo.svg', $this->createResolver()->resolveUrl($file));
    }

    private function createResolver(): FileUrlResolver
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
        $cloudFront = new CloudFrontUrlGenerator(new CloudFrontClient([
            'region' => 'eu-central-1',
            'version' => '2020-05-31',
            'credentials' => false,
        ]), 3600);

        return new FileUrlResolver(
            new UrlSigner($s3Client, 'databox', 3600, $cloudFront),
            new ArrayAdapter(),
            new TemporaryCacheFactory(),
        );
    }

    private function createFile(string $path, ?string $type): File
    {
        $file = new File();
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setPath($path);
        $file->setType($type);

        return $file;
    }

    private function getQuery(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query;
    }
}
