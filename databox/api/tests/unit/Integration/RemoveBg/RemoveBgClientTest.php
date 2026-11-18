<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\RemoveBg;

use Alchemy\CoreBundle\Listener\ClientExceptionListener;
use App\Entity\Core\File;
use App\Integration\RemoveBg\RemoveBgClient;
use App\Service\Asset\FileFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class RemoveBgClientTest extends TestCase
{
    private string $path;
    private string $cacheDir;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'removebg');
        file_put_contents($this->path, 'image-bytes');
        $this->cacheDir = sys_get_temp_dir().'/removebg-cache-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        unlink($this->path);
        array_map(unlink(...), glob($this->cacheDir.'/*') ?: []);
        if (is_dir($this->cacheDir)) {
            rmdir($this->cacheDir);
        }
    }

    public function testTheImageIsSentAsAStringMultipartBody(): void
    {
        $sent = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent = $options;

            return new MockResponse('png-bytes');
        }, 'https://api.remove.bg');

        $result = $this->createClient($client)->getBgRemoved(new File(), 'api-key');

        $this->assertSame('png-bytes', file_get_contents($result));
        // A string body is what lets curl send the request again on a fresh connection
        $this->assertIsString($sent['body']);
        $this->assertStringContainsString('name="image_file"', $sent['body']);
        $this->assertStringContainsString('image-bytes', $sent['body']);
        $this->assertStringContainsString('name="size"', $sent['body']);
        $this->assertContains('X-Api-Key: api-key', $sent['headers']);

        $contentType = array_values(array_filter(
            $sent['headers'],
            static fn (string $header): bool => str_starts_with(strtolower($header), 'content-type:'),
        ));
        $this->assertCount(1, $contentType);
        $this->assertSame(1, preg_match('#multipart/form-data; boundary=(\S+)#', $contentType[0], $matches));
        $this->assertStringContainsString('--'.$matches[1], $sent['body']);
    }

    public function testAnImageTooLargeForRemoveBgIsNotSent(): void
    {
        // Sparse file: its size is reported without the bytes being written
        $fd = fopen($this->path, 'r+');
        ftruncate($fd, 23 * 1024 * 1024);
        fclose($fd);

        $client = new MockHttpClient([]);

        try {
            $this->createClient($client)->getBgRemoved(new File(), 'api-key');
            $this->fail('The image should be refused');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('remove.bg accepts at most', $e->getMessage());
            $this->assertSame(0, $client->getRequestsCount());
        }
    }

    private function createClient(MockHttpClient $client): RemoveBgClient
    {
        $fileFetcher = $this->createStub(FileFetcher::class);
        $fileFetcher->method('getFile')->willReturn($this->path);

        return new RemoveBgClient($fileFetcher, $this->cacheDir, $client, new ClientExceptionListener());
    }
}
