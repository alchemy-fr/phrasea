<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Asset;

use Alchemy\CoreBundle\Cache\TemporaryCacheFactory;
use Alchemy\StorageBundle\Cdn\CloudFrontUrlGenerator;
use Alchemy\StorageBundle\Storage\FileStorageManager;
use Alchemy\StorageBundle\Storage\ObjectDownloader;
use Alchemy\StorageBundle\Storage\UrlSigner;
use Alchemy\StorageBundle\Upload\ContentTypeDetector;
use Alchemy\StorageBundle\Upload\FileValidator;
use Alchemy\StorageBundle\Upload\MultipartUploadPlanner;
use App\Border\Exception\UnsupportedUriException;
use App\Border\UriDownloader;
use App\Entity\Core\File;
use App\Service\Asset\Exception\PrivateFileException;
use App\Service\Asset\Exception\RefusedFileContentException;
use App\Service\Asset\FileFetcher;
use App\Service\Asset\FileUrlResolver;
use App\Service\Storage\TemporaryFiles;
use App\Tests\Functional\Storage\LocalS3Handler;
use Aws\CloudFront\CloudFrontClient;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Filesystem\Filesystem as LocalFilesystem;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class FileFetcherTest extends TestCase
{
    private const string PNG = "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\rIDATx\xdac\xf8\xcf\xc0\xf0\x1f\x00\x05\x00\x01\xff\x89\x99=\x1d\x00\x00\x00\x00IEND\xaeB`\x82";
    private const string GIF = "GIF89a\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";
    private const string HTML = '<!DOCTYPE html><html><body><script>alert(document.cookie)</script></body></html>';

    private string $dir;
    private FileStorageManager $storageManager;
    private Filesystem $storage;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/'.uniqid('file-fetcher-test');
        mkdir($this->dir);
        $this->storage = new Filesystem(new LocalFilesystemAdapter($this->dir.'/storage'));
        $this->storageManager = new FileStorageManager($this->storage);
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
        $file->setDetectedType(new FileValidator('')->validateHead($head, $file->getFileName(), $file->getType())->detectedType);
        $this->assertSame('image/png', $file->getDetectedType());

        return $file;
    }

    public function testRemoteFileWithAnotherSchemeIsNotFetched(): void
    {
        $fetcher = $this->createFetcher(new MockHttpClient(function (): never {
            $this->fail('No request expected');
        }));

        $this->expectException(UnsupportedUriException::class);
        $fetcher->getFile($this->createRemoteFile('javascript:alert(1)'));
    }

    public function testManyFilesAreFetchedFromTheStorageAndFromUrls(): void
    {
        $fetcher = $this->createFetcher(new MockHttpClient(static fn (string $method, string $url): MockResponse => new MockResponse('remote '.basename($url))));
        $stored = $this->createStoredFile('a/b/foo.txt', 'foo content');
        $otherStored = $this->createStoredFile('a/c/bar.txt', 'bar content');
        $remote = $this->createRemoteFile('https://example.com/a.txt');

        $files = [
            $this->dir.'/foo.txt' => $stored,
            $this->dir.'/remote.txt' => $remote,
            $this->dir.'/bar.txt' => $otherStored,
            $this->dir.'/foo-again.txt' => $stored,
        ];
        $this->assertSame([], $fetcher->getFiles($files, 2));

        $this->assertSame('foo content', file_get_contents($this->dir.'/foo.txt'));
        $this->assertSame('foo content', file_get_contents($this->dir.'/foo-again.txt'));
        $this->assertSame('bar content', file_get_contents($this->dir.'/bar.txt'));
        $this->assertSame('remote a.txt', file_get_contents($this->dir.'/remote.txt'));
        $this->assertSame($this->dir.'/remote.txt', $remote->localTmpPath);
        $this->assertSame($this->dir.'/bar.txt', $otherStored->localTmpPath);

        $fetcher->reset();
        $this->assertFileExists($this->dir.'/foo.txt', 'the paths of the caller are not temporary');
        $this->assertFileExists($this->dir.'/remote.txt', 'the paths of the caller are not temporary');
    }

    public function testFailuresOfManyFilesAreReturnedWithoutStoppingTheOthers(): void
    {
        $fetcher = $this->createFetcher(new MockHttpClient(static fn (string $method, string $url): MockResponse => match (parse_url($url, PHP_URL_PATH)) {
            '/pixel.png' => new MockResponse(self::HTML),
            '/missing.png' => new MockResponse('', ['http_code' => 404]),
            default => new MockResponse('remote content'),
        }));
        $stored = $this->createStoredFile('a/b/foo.txt', 'foo content');
        $missingStored = new File();
        $missingStored->setStorage(File::STORAGE_S3_MAIN);
        $missingStored->setPath('a/b/missing.txt');
        $private = $this->createRemoteFile('https://example.com/private.png');
        $private->setPathPublic(false);
        $refused = $this->createRemoteFile('https://example.com/pixel.png');
        $refused->setType('image/png');
        $refused->setOriginalName('pixel.png');
        $missingRemote = $this->createRemoteFile('https://example.com/missing.png');
        $unsupported = $this->createRemoteFile('javascript:alert(1)');
        $remote = $this->createRemoteFile('https://example.com/a.txt');

        $failures = $fetcher->getFiles([
            $this->dir.'/foo.txt' => $stored,
            $this->dir.'/missing.txt' => $missingStored,
            $this->dir.'/private.png' => $private,
            $this->dir.'/refused.png' => $refused,
            $this->dir.'/missing.png' => $missingRemote,
            $this->dir.'/unsupported' => $unsupported,
            $this->dir.'/remote.txt' => $remote,
        ], 3);

        $this->assertInstanceOf(S3Exception::class, $failures[$this->dir.'/missing.txt']);
        $this->assertInstanceOf(PrivateFileException::class, $failures[$this->dir.'/private.png']);
        $this->assertInstanceOf(RefusedFileContentException::class, $failures[$this->dir.'/refused.png']);
        $this->assertInstanceOf(ClientException::class, $failures[$this->dir.'/missing.png']);
        $this->assertInstanceOf(UnsupportedUriException::class, $failures[$this->dir.'/unsupported']);
        $this->assertCount(5, $failures);

        foreach (array_keys($failures) as $path) {
            $this->assertFileDoesNotExist($path);
        }
        $this->assertNull($refused->localTmpPath, 'the refused copy is not reused');
        $this->assertNull($missingStored->localTmpPath);

        $this->assertSame('foo content', file_get_contents($this->dir.'/foo.txt'));
        $this->assertSame('remote content', file_get_contents($this->dir.'/remote.txt'));
    }

    private function createFetcher(?MockHttpClient $httpClient = null): FileFetcher
    {
        $temporaryFiles = new TemporaryFiles($this->dir.'/tmp');

        return new FileFetcher(
            $this->createUrlResolver(),
            // "dev" lets the mocked responses through, without resolving the host to check the network is public.
            new UriDownloader($httpClient ?? new MockHttpClient(), 'dev', new MultipartUploadPlanner(1, 1024, 1024, 1024 * 1024), 900, $temporaryFiles),
            $this->storageManager,
            new FileValidator(''),
            new ObjectDownloader(LocalS3Handler::install(new S3Client([
                'version' => 'latest',
                'region' => 'us-east-1',
                'credentials' => ['key' => 'key', 'secret' => 'secret'],
            ]), $this->storage, ''), 'databox'),
            $temporaryFiles,
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
