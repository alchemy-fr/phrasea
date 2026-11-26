<?php

declare(strict_types=1);

namespace App\Tests\Unit\Border;

use Alchemy\CoreBundle\Exception\DownloadTooLargeException;
use Alchemy\StorageBundle\Upload\MultipartUploadPlanner;
use App\Border\Exception\UnsupportedUriException;
use App\Border\UriDownloader;
use App\Service\Storage\TemporaryFiles;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\ClientException;
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

    public function testARefusedRangeIsRequestedAgainWithoutRange(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = $options['normalized_headers']['range'] ?? null;

            return 1 === \count($requests)
                ? new MockResponse('', ['http_code' => 416])
                : new MockResponse('abcdef');
        });
        $downloader = new UriDownloader($client, 'prod', new MultipartUploadPlanner(1, 1024, 1024, 1024), 900, new TemporaryFiles(sys_get_temp_dir()));

        $this->assertSame('abc', $downloader->downloadHead('http://10.0.0.1/file.jpg', 3, allowPrivateNetwork: true));
        $this->assertSame([['Range: bytes=0-2'], null], $requests);
    }

    public function testAnErrorPageIsNotTakenForTheHead(): void
    {
        $downloader = $this->createDownloader(new MockResponse('<!doctype html>Not found', ['http_code' => 404]));

        $this->expectException(ClientException::class);
        $downloader->downloadHead('http://10.0.0.1/file.png', 3, allowPrivateNetwork: true);
    }

    public function testManyDownloadsWriteEachContentToItsPath(): void
    {
        $downloader = $this->createRoutingDownloader([
            '/a.jpg' => new MockResponse('content a'),
            '/b.jpg' => new MockResponse(''),
            '/c.jpg' => new MockResponse(['content ', 'c']),
        ]);

        $paths = [$this->createTempPath(), $this->createTempPath(), $this->createTempPath()];
        try {
            $failures = $downloader->downloadMany([
                $paths[0] => 'http://10.0.0.1/a.jpg',
                $paths[1] => 'http://10.0.0.1/b.jpg',
                $paths[2] => 'http://10.0.0.1/c.jpg',
            ], 2, allowPrivateNetwork: true);

            $this->assertSame([], $failures);
            $this->assertSame('content a', file_get_contents($paths[0]));
            $this->assertSame('', file_get_contents($paths[1]));
            $this->assertSame('content c', file_get_contents($paths[2]));
        } finally {
            array_map(static fn (string $path) => @unlink($path), $paths);
        }
    }

    public function testManyDownloadsIsolateTheFailures(): void
    {
        // No Content-Length: only the received bytes tell the size
        $tooLarge = (static function (): \Generator {
            for ($i = 0; $i < 5; ++$i) {
                yield str_repeat('a', 4);
            }
        })();
        $requested = [];
        $downloader = $this->createRoutingDownloader([
            '/ok.jpg' => new MockResponse('ok'),
            '/missing.jpg' => new MockResponse('<!doctype html>Not found', ['http_code' => 404]),
            '/large.jpg' => new MockResponse($tooLarge),
            '/declared.jpg' => new MockResponse(str_repeat('a', 11), ['response_headers' => ['Content-Length' => '11']]),
            '/last.jpg' => new MockResponse('last'),
        ], 10, $requested);

        $ok = $this->createTempPath();
        $missing = $this->createTempPath();
        $large = $this->createTempPath();
        $declared = $this->createTempPath();
        $unsupported = $this->createTempPath();
        $last = $this->createTempPath();
        try {
            $failures = $downloader->downloadMany([
                $ok => 'http://10.0.0.1/ok.jpg',
                $missing => 'http://10.0.0.1/missing.jpg',
                $large => 'http://10.0.0.1/large.jpg?X-Amz-Signature=secret',
                $declared => 'http://10.0.0.1/declared.jpg',
                $unsupported => 'file:///etc/passwd',
                $last => 'http://10.0.0.1/last.jpg',
            ], 2, allowPrivateNetwork: true);

            $this->assertEqualsCanonicalizing([$missing, $large, $declared, $unsupported], array_keys($failures));
            $this->assertInstanceOf(ClientException::class, $failures[$missing]);
            $this->assertInstanceOf(DownloadTooLargeException::class, $failures[$large]);
            $this->assertStringNotContainsString('secret', $failures[$large]->getMessage());
            $this->assertInstanceOf(DownloadTooLargeException::class, $failures[$declared]);
            $this->assertStringContainsString('11 bytes', $failures[$declared]->getMessage());
            $this->assertInstanceOf(UnsupportedUriException::class, $failures[$unsupported]);

            $this->assertSame('ok', file_get_contents($ok));
            $this->assertSame('last', file_get_contents($last));
            foreach ([$missing, $large, $declared, $unsupported] as $path) {
                $this->assertFileDoesNotExist($path);
            }
            $this->assertNotContains('file:///etc/passwd', $requested, 'An unsupported URI is never requested');
            $this->assertCount(5, $requested);
        } finally {
            array_map(static fn (string $path) => @unlink($path), [$ok, $last]);
        }
    }

    public function testManyDownloadsKeepAtMostTheConcurrencyInFlight(): void
    {
        $inFlight = 0;
        $maxInFlight = 0;
        $client = new MockHttpClient(static function () use (&$inFlight, &$maxInFlight): MockResponse {
            $maxInFlight = max($maxInFlight, ++$inFlight);
            $body = (static function () use (&$inFlight): \Generator {
                yield 'a';
                yield 'b';
                --$inFlight;
            })();

            return new MockResponse($body);
        });
        $downloader = new UriDownloader($client, 'prod', new MultipartUploadPlanner(1, 1024, 1024, 1024), 900, new TemporaryFiles(sys_get_temp_dir()));

        $uris = [];
        for ($i = 0; $i < 7; ++$i) {
            $uris[$this->createTempPath()] = 'http://10.0.0.1/'.$i.'.jpg';
        }
        try {
            $this->assertSame([], $downloader->downloadMany($uris, 3, allowPrivateNetwork: true));
            $this->assertSame(3, $maxInFlight);
            foreach (array_keys($uris) as $path) {
                $this->assertSame('ab', file_get_contents($path));
            }
        } finally {
            array_map(static fn (string $path) => @unlink($path), array_keys($uris));
        }
    }

    public function testTheBodyWrittenToAFileIsNotBuffered(): void
    {
        $buffers = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$buffers): MockResponse {
            $buffers[parse_url($url, PHP_URL_PATH)] = $options['buffer'];

            return new MockResponse('content');
        });
        $downloader = new UriDownloader($client, 'prod', new MultipartUploadPlanner(1, 1024, 1024, 1024), 900, new TemporaryFiles(sys_get_temp_dir()));
        $paths = [$this->createTempPath(), $this->createTempPath()];

        try {
            $downloader->download('http://10.0.0.1/one.jpg', path: $paths[0], allowPrivateNetwork: true);
            $this->assertSame([], $downloader->downloadMany([$paths[1] => 'http://10.0.0.1/many.jpg'], 1, allowPrivateNetwork: true));
        } finally {
            array_map(static fn (string $path) => @unlink($path), $paths);
        }

        $this->assertSame(['/one.jpg' => false, '/many.jpg' => false], $buffers);
    }

    /**
     * @param array<string, MockResponse> $responses keyed by URL path
     * @param string[]                    $requested the URLs requested
     */
    private function createRoutingDownloader(array $responses, int $maxSize = 1024 * 1024, array &$requested = []): UriDownloader
    {
        $client = new MockHttpClient(static function (string $method, string $url) use ($responses, &$requested): MockResponse {
            $requested[] = $url;

            return $responses[parse_url($url, PHP_URL_PATH)] ?? new MockResponse('', ['http_code' => 404]);
        });

        return new UriDownloader($client, 'prod', new MultipartUploadPlanner(1, $maxSize, $maxSize, $maxSize), 900, new TemporaryFiles(sys_get_temp_dir()));
    }

    private function createDownloader(MockResponse $response, int $maxSize = 1024 * 1024): UriDownloader
    {
        return new UriDownloader(
            new MockHttpClient($response),
            'prod',
            new MultipartUploadPlanner(1, $maxSize, $maxSize, $maxSize),
            900,
            new TemporaryFiles(sys_get_temp_dir()),
        );
    }

    private function createTempPath(): string
    {
        return sys_get_temp_dir().'/'.uniqid('uri-downloader-test');
    }
}
