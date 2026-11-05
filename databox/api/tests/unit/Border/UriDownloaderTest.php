<?php

declare(strict_types=1);

namespace App\Tests\Unit\Border;

use App\Border\UriDownloader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class UriDownloaderTest extends TestCase
{
    public function testPrivateNetworkUrlsAreRefused(): void
    {
        $downloader = new UriDownloader(new MockHttpClient(new MockResponse('secret')), 'prod');

        $this->expectException(TransportException::class);
        $downloader->download('http://169.254.169.254/latest/meta-data/');
    }

    public function testTrustedUrlsMayReachThePrivateNetwork(): void
    {
        $downloader = new UriDownloader(new MockHttpClient(new MockResponse('content')), 'prod');

        $path = $downloader->download('http://10.0.0.1/file.jpg', allowPrivateNetwork: true);
        try {
            $this->assertSame('content', file_get_contents($path));
        } finally {
            unlink($path);
        }
    }
}
