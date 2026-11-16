<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Asset;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use Alchemy\StorageBundle\Cdn\CloudFrontUrlGenerator;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\UrlSigner;
use Alchemy\StorageBundle\Upload\ContentTypeDetector;
use Alchemy\StorageBundle\Upload\FileValidator;
use Alchemy\StorageBundle\Upload\MultipartUploadPlanner;
use App\Border\UriDownloader;
use App\Entity\Core\File;
use App\Service\Asset\Exception\RefusedFileContentException;
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
    private const string PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\xdac\xf8\xcf\xc0\xf0\x1f\x00\x05\x00\x01\xff\x89\x99=\x1d\x00\x00\x00\x00IEND\xaeB`\x82";
    private const string GIF = "GIF89a\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";
    private const string HTML = '<!DOCTYPE html><html><body><script>alert(document.cookie)</script></body></html>';

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

    public function testRemoteContentIsCheckedAgainOnDownload(): void
    {
        $fetcher = $this->createFetcher($this->createHeadThenBodyClient(self::PNG, self::PNG));
        $file = $this->createCheckedRemoteFile($fetcher);

        $path = $fetcher->getFile($file);

        $this->assertSame(self::PNG, file_get_contents($path));
    }

    public function testRemoteContentDifferentFromTheCheckedHeadIsRefused(): void
    {
        $fetcher = $this->createFetcher($this->createHeadThenBodyClient(self::PNG, self::HTML));
        $file = $this->createCheckedRemoteFile($fetcher);
        $downloads = glob(sys_get_temp_dir().'/download-file*') ?: [];

        try {
            $fetcher->getFile($file);
            $this->fail('The content must be refused');
        } catch (RefusedFileContentException $e) {
            $this->assertStringContainsString('text/html', $e->getMessage());
        }

        $this->assertNull($file->localTmpPath, 'the refused copy is not reused');
        $this->assertSame($downloads, glob(sys_get_temp_dir().'/download-file*') ?: [], 'the refused copy is deleted');
    }

    public function testRemoteContentOfAnotherTypeOfTheSameFamilyIsRefused(): void
    {
        $fetcher = $this->createFetcher($this->createHeadThenBodyClient(self::PNG, self::GIF));
        $file = $this->createCheckedRemoteFile($fetcher);

        $this->expectException(RefusedFileContentException::class);
        $this->expectExceptionMessage('Content is "image/gif", "image/png" was accepted');
        $fetcher->getFile($file);
    }

    public function testRemoteContentOfABypassedFileIsNotCheckedAgain(): void
    {
        $fetcher = $this->createFetcher($this->createHeadThenBodyClient(self::HTML, self::HTML));
        $file = $this->createRemoteFile('https://example.com/pixel.png');
        $file->setType('image/png');
        $file->rejectContent('Declared type "image/png", content is "text/html"');
        $file->bypassAnalysis();

        $this->assertSame(self::HTML, file_get_contents($fetcher->getFile($file)));
    }

    /**
     * A server answering the head requests (Range) of the content check with $head, the full downloads with $body.
     */
    private function createHeadThenBodyClient(string $head, string $body): MockHttpClient
    {
        return new MockHttpClient(static fn (string $method, string $url, array $options): MockResponse => new MockResponse(
            isset($options['normalized_headers']['range']) ? $head : $body,
        ));
    }

    /**
     * A remote file whose head was accepted, as CheckFileContentAction does.
     */
    private function createCheckedRemoteFile(FileFetcher $fetcher): File
    {
        $file = $this->createRemoteFile('https://example.com/pixel.png');
        $file->setType('image/png');
        $file->setOriginalName('pixel.png');

        $head = $fetcher->getHead($file, ContentTypeDetector::HEAD_SIZE);
        $file->setDetectedType(new FileValidator('')->validateHead($head, $file->getFileName(), $file->getType()));
        $this->assertSame('image/png', $file->getDetectedType());

        return $file;
    }

    private function createFetcher(?MockHttpClient $httpClient = null): FileFetcher
    {
        return new FileFetcher(
            $this->createUrlResolver(),
            // "dev" lets the mocked responses through, without resolving the host to check the network is public.
            new UriDownloader($httpClient ?? new MockHttpClient(), 'dev', new MultipartUploadPlanner(1, 1024, 1024, 1024 * 1024), 900),
            $this->storageManager,
            new FileValidator(''),
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
