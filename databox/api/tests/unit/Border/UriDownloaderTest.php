<?php

declare(strict_types=1);

namespace App\Tests\Unit\Border;

use Alchemy\CoreBundle\Exception\DownloadTooLargeException;
use Alchemy\StorageBundle\Upload\MultipartUploadPlanner;
use App\Border\UriDownloader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class UriDownloaderTest extends TestCase
{
    public function testPrivateNetworkUrlsAreRefused(): void
    {
        $downloader = $this->createDownloader(new MockResponse('secret'));

        $this->expectException(TransportException::class);
        $downloader->download('http://169.254.169.254/latest/meta-data/');
    }

    public function testTrustedUrlsMayReachThePrivateNetwork(): void
    {
        $downloader = $this->createDownloader(new MockResponse('content'));

        $path = $downloader->download('http://10.0.0.1/file.jpg', allowPrivateNetwork: true);
        try {
            $this->assertSame('content', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }

    public function testContentAtTheMaximumSizeIsDownloaded(): void
    {
        $downloader = $this->createDownloader(new MockResponse(str_repeat('a', 10)), maxSize: 10);

        $path = $downloader->download('http://10.0.0.1/file.jpg', allowPrivateNetwork: true);
        try {
            $this->assertSame(10, filesize($path));
        } finally {
            unlink($path);
        }
    }

    public function testDeclaredSizeAboveTheMaximumIsRefused(): void
    {
        $downloader = $this->createDownloader(new MockResponse(str_repeat('a', 11), [
            'response_headers' => ['Content-Length' => '11'],
        ]), maxSize: 10);

        $path = $this->createTempPath();
        try {
            $downloader->download('http://10.0.0.1/file.jpg', path: $path, allowPrivateNetwork: true);
            $this->fail('The download should have been refused');
        } catch (DownloadTooLargeException $e) {
            $this->assertStringContainsString('11 bytes', $e->getMessage());
        }
        $this->assertFileDoesNotExist($path);
    }

    public function testContentAboveTheMaximumIsCutAndRemoved(): void
    {
        // No Content-Length: only the received bytes tell the size
        $body = (static function (): \Generator {
            for ($i = 0; $i < 5; ++$i) {
                yield str_repeat('a', 4);
            }
        })();
        $downloader = $this->createDownloader(new MockResponse($body), maxSize: 10);

        $path = $this->createTempPath();
        try {
            $downloader->download('http://10.0.0.1/file.jpg?X-Amz-Signature=secret', path: $path, allowPrivateNetwork: true);
            $this->fail('The download should have been interrupted');
        } catch (DownloadTooLargeException $e) {
            $this->assertStringNotContainsString('secret', $e->getMessage());
        }
        $this->assertFileDoesNotExist($path);
    }

    private function createDownloader(MockResponse $response, int $maxSize = 1024 * 1024): UriDownloader
    {
        return new UriDownloader(
            new MockHttpClient($response),
            'prod',
            new MultipartUploadPlanner(1, $maxSize, $maxSize, $maxSize),
            900,
        );
    }

    private function createTempPath(): string
    {
        return sys_get_temp_dir().'/'.uniqid('uri-downloader-test');
    }
}
