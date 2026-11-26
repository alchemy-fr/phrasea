<?php

declare(strict_types=1);

namespace Alchemy\CoreBundle\Tests\Util;

use Alchemy\CoreBundle\Util\HttpDownloadUtil;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class HttpDownloadUtilTest extends TestCase
{
    private const string SCHEME = 'short-write';

    protected function setUp(): void
    {
        ShortWriteStream::$unlinked = [];
        stream_wrapper_register(self::SCHEME, ShortWriteStream::class);
    }

    protected function tearDown(): void
    {
        stream_wrapper_unregister(self::SCHEME);
    }

    public function testTheContentIsWrittenToTheFile(): void
    {
        $client = new MockHttpClient(new MockResponse(['abc', 'def']));
        $path = sys_get_temp_dir().'/'.uniqid('http-download-test');

        try {
            HttpDownloadUtil::streamToFile($client, $client->request('GET', 'https://example.com/file'), $path, 1024);
            $this->assertSame('abcdef', file_get_contents($path));
        } finally {
            @unlink($path);
        }
    }

    public function testAShortWriteFailsAndRemovesThePartialFile(): void
    {
        $client = new MockHttpClient(new MockResponse(['abc', 'def']));
        $path = self::SCHEME.'://file';

        try {
            HttpDownloadUtil::streamToFile($client, $client->request('GET', 'https://example.com/file'), $path, 1024);
            $this->fail('A short write must fail');
        } catch (\RuntimeException $e) {
            $this->assertSame(sprintf('Unable to write "%s"', $path), $e->getMessage());
        }

        $this->assertSame([$path], ShortWriteStream::$unlinked);
    }
}

/**
 * A file on a full disk: each write stores less than it is given.
 */
class ShortWriteStream
{
    /**
     * @var string[]
     */
    public static array $unlinked = [];

    /**
     * @var resource|null
     */
    public $context;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_write(string $data): int
    {
        return intdiv(strlen($data), 2);
    }

    public function stream_close(): void
    {
    }

    public function stream_flush(): bool
    {
        return true;
    }

    public function unlink(string $path): bool
    {
        self::$unlinked[] = $path;

        return true;
    }
}
