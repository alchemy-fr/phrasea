<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Vector;

use Alchemy\CoreBundle\Listener\ClientExceptionListener;
use App\Service\Fixture\FixtureAnalysisCache;
use App\Service\Vector\EmbedderClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

class EmbedderClientTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'embed');
        file_put_contents($this->path, 'image-bytes');
    }

    protected function tearDown(): void
    {
        unlink($this->path);
    }

    public function testTheImageIsSentAsAStringMultipartBody(): void
    {
        $sent = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent): JsonMockResponse {
            $sent = $options;

            return new JsonMockResponse(['vector' => [0.1], 'model' => 'test', 'dim' => 1]);
        }, 'http://embedder');

        $result = new EmbedderClient($client, new ClientExceptionListener(), new FixtureAnalysisCache(false, sys_get_temp_dir()))->embedImageFile($this->path);

        $this->assertSame('test', $result['model']);
        // A string body is what lets curl send the request again on a fresh connection
        $this->assertIsString($sent['body']);
        $this->assertStringContainsString('name="file"', $sent['body']);
        $this->assertStringContainsString('image-bytes', $sent['body']);

        $contentType = array_values(array_filter(
            $sent['headers'],
            static fn (string $header): bool => str_starts_with(strtolower($header), 'content-type:'),
        ));
        $this->assertCount(1, $contentType);
        $this->assertMatchesRegularExpression('#multipart/form-data; boundary=#', $contentType[0]);
        $this->assertSame(1, preg_match('#boundary=(\S+)#', $contentType[0], $matches));
        $this->assertStringContainsString('--'.$matches[1], $sent['body']);
    }

    public function testAnEnabledFixtureCacheSendsTheSameImageOnce(): void
    {
        $cacheDir = sys_get_temp_dir().'/embed-cache-'.bin2hex(random_bytes(6));
        $requests = 0;
        $client = new MockHttpClient(function () use (&$requests): JsonMockResponse {
            ++$requests;

            return new JsonMockResponse(['vector' => [0.5, 1.0], 'model' => 'test', 'dim' => 2]);
        }, 'http://embedder');

        try {
            $embedder = new EmbedderClient($client, new ClientExceptionListener(), new FixtureAnalysisCache(true, $cacheDir));

            $first = $embedder->embedImageFile($this->path);
            $second = $embedder->embedImageFile($this->path);

            $this->assertSame(1, $requests);
            $this->assertSame([0.5, 1.0], $second['vector']);
            $this->assertSame($first, $second);
        } finally {
            array_map(unlink(...), glob($cacheDir.'/similarity/*') ?: []);
            @rmdir($cacheDir.'/similarity');
            @rmdir($cacheDir);
        }
    }
}
