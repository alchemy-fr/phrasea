<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Asset;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use Alchemy\StorageBundle\Cdn\CloudFrontUrlGenerator;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\UrlSigner;
use App\Border\UriDownloader;
use App\Entity\Core\File;
use App\Service\Asset\FileFetcher;
use App\Service\Asset\FileUrlResolver;
use Aws\CloudFront\CloudFrontClient;
use Aws\S3\S3Client;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem as LocalFilesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class FileFetcherTest extends TestCase
{
    private string $dir;
    private FileStorageManager $storageManager;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/'.uniqid('file-fetcher-test');
        mkdir($this->dir);
        $this->storageManager = new FileStorageManager(new Filesystem(new LocalFilesystemAdapter($this->dir.'/storage')));
    }

    protected function tearDown(): void
    {
        (new LocalFilesystem())->remove($this->dir);
    }

    public function testStorageCopyIsDeletedOnReset(): void
    {
        $fetcher = $this->createFetcher();
        $file = $this->createStoredFile('a/b/foo.txt', 'foo content');

        $path = $fetcher->getFile($file);
        $this->assertStringContainsString('fetch-file', $path);
        $this->assertSame('foo content', file_get_contents($path));
        $this->assertSame($path, $fetcher->getFile($file));

        $fetcher->reset();

        $this->assertFileDoesNotExist($path);
    }

    public function testFileIsFetchedAgainAfterReset(): void
    {
        $fetcher = $this->createFetcher();
        $file = $this->createStoredFile('a/b/foo.txt', 'foo content');

        $path = $fetcher->getFile($file);
        $fetcher->reset();

        $newPath = $fetcher->getFile($file);
        $this->assertNotSame($path, $newPath);
        $this->assertSame('foo content', file_get_contents($newPath));

        $fetcher->reset();
        $this->assertFileDoesNotExist($newPath);
    }

    public function testCallerPathIsKept(): void
    {
        $fetcher = $this->createFetcher();
        $file = $this->createStoredFile('a/b/foo.txt', 'foo content');
        $path = $this->dir.'/export.txt';

        $this->assertSame($path, $fetcher->getFile($file, $path));

        $fetcher->reset();

        $this->assertFileExists($path);
        $this->assertSame('foo content', file_get_contents($path));
    }

    public function testDownloadedCopyIsDeletedOnReset(): void
    {
        $fetcher = $this->createFetcher(new MockHttpClient(new MockResponse('remote content')));
        $file = $this->createRemoteFile('https://example.com/a.jpg');

        $path = $fetcher->getFile($file);
        $this->assertStringContainsString('download-file', $path);
        $this->assertSame('remote content', file_get_contents($path));

        $fetcher->reset();

        $this->assertFileDoesNotExist($path);
    }

    public function testDownloadFileCopyIsDeletedOnReset(): void
    {
        $fetcher = $this->createFetcher(new MockHttpClient(new MockResponse('remote content', [
            'response_headers' => ['Content-Type' => 'image/jpeg'],
        ])));
        $file = $this->createRemoteFile('https://example.com/a.jpg');

        $headers = [];
        $path = $fetcher->downloadFile($file, $headers);
        $this->assertSame('remote content', file_get_contents($path));
        $this->assertSame(['image/jpeg'], $headers['content-type']);

        $fetcher->reset();

        $this->assertFileDoesNotExist($path);
    }

    public function testCopyAlreadyDeletedByCallerIsIgnored(): void
    {
        $fetcher = $this->createFetcher();
        $file = $this->createStoredFile('a/b/foo.txt', 'foo content');

        unlink($fetcher->getFile($file));

        $fetcher->reset();
        $this->addToAssertionCount(1);
    }

    public function testCopiesAreDeletedOnDestruction(): void
    {
        $fetcher = $this->createFetcher();
        $path = $fetcher->getFile($this->createStoredFile('a/b/foo.txt', 'foo content'));
        $this->assertFileExists($path);

        unset($fetcher);

        $this->assertFileDoesNotExist($path);
    }

    private function createFetcher(?MockHttpClient $httpClient = null): FileFetcher
    {
        return new FileFetcher(
            $this->createUrlResolver(),
            // "dev" lets the mocked responses through, without resolving the host to check the network is public.
            new UriDownloader($httpClient ?? new MockHttpClient(), 'dev'),
            $this->storageManager,
        );
    }

    private function createStoredFile(string $path, string $content): File
    {
        $this->storageManager->store($path, $content);

        $file = new File();
        $file->setStorage(File::STORAGE_S3_MAIN);
        $file->setPath($path);

        return $file;
    }

    private function createRemoteFile(string $url): File
    {
        $file = new File();
        $file->setStorage(File::STORAGE_URL);
        $file->setPath($url);
        $file->setPathPublic(true);

        return $file;
    }

    private function createUrlResolver(): FileUrlResolver
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
}
